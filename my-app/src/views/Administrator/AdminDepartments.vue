<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

// --- State ---
interface Abteilung {
  AbteilungID: number
  name: string
}

const departments = ref<Abteilung[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)

// State für "Neu Hinzufügen"
const newDeptName = ref('')

// State für "Bearbeiten" (Dialog)
const showEditDialog = ref(false)
const editDeptName = ref('')
const editDeptId = ref<number | null>(null)
const isUpdating = ref(false)

// State für Mehrfachauswahl
const selectedIds = ref<number[]>([])
const isDeletingBulk = ref(false)

const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// --- Navigation ---
function goBack() {
  router.push({ name: 'Dashboard' })
}

// --- API ---
const API_URL = import.meta.env.VITE_API_URL + '/api/admin/abteilungen'

async function fetchDepartments() {
  isLoading.value = true
  try {
    const response = await axios.get<Abteilung[]>(API_URL)
    departments.value = response.data
    selectedIds.value = [] // Auswahl zurücksetzen beim Laden
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Fehler beim Laden der Abteilungen.'
  } finally {
    isLoading.value = false
  }
}

// --- Computed: Alle auswählen ---
const allSelected = computed({
  get: () => departments.value.length > 0 && selectedIds.value.length === departments.value.length,
  set: (val: boolean) => {
    selectedIds.value = val ? departments.value.map(d => d.AbteilungID) : []
  }
})

async function addDepartment() {
  if (!newDeptName.value.trim()) return

  isSubmitting.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const response = await axios.post(API_URL, { name: newDeptName.value })
    departments.value.push(response.data)
    departments.value.sort((a, b) => a.name.localeCompare(b.name))
    newDeptName.value = ''
    successMessage.value = 'Abteilung erfolgreich hinzugefügt.'
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Fehler beim Hinzufügen.'
  } finally {
    isSubmitting.value = false
  }
}

function openEditDialog(dept: Abteilung) {
  editDeptId.value = dept.AbteilungID
  editDeptName.value = dept.name
  showEditDialog.value = true
  errorMessage.value = null
}

async function updateDepartment() {
  if (!editDeptName.value.trim() || !editDeptId.value) return

  isUpdating.value = true
  errorMessage.value = null

  try {
    await axios.put(`${API_URL}/${editDeptId.value}`, {
      name: editDeptName.value
    })

    const index = departments.value.findIndex(d => d.AbteilungID === editDeptId.value)
    if (index !== -1) {
      departments.value[index].name = editDeptName.value
      departments.value.sort((a, b) => a.name.localeCompare(b.name))
    }

    successMessage.value = 'Abteilung erfolgreich umbenannt.'
    showEditDialog.value = false

  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Fehler beim Aktualisieren.'
  } finally {
    isUpdating.value = false
  }
}

async function deleteDepartment(id: number, name: string) {
  if (!confirm(`Möchtest du die Abteilung "${name}" wirklich löschen?`)) return

  isLoading.value = true // Kleiner Ladezustand
  errorMessage.value = null
  successMessage.value = null

  try {
    await axios.delete(`${API_URL}/${id}`)
    departments.value = departments.value.filter(d => d.AbteilungID !== id)
    // Falls ID ausgewählt war, entfernen
    selectedIds.value = selectedIds.value.filter(selId => selId !== id)
    successMessage.value = `Abteilung "${name}" wurde gelöscht.`
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Löschen fehlgeschlagen.'
  } finally {
    isLoading.value = false
  }
}

// --- NEU: Massen-Löschen Funktion ---
async function deleteSelected() {
  const count = selectedIds.value.length
  if (count === 0) return
  if (!confirm(`Möchtest du wirklich ${count} Abteilungen endgültig löschen?`)) return

  isDeletingBulk.value = true
  errorMessage.value = null
  successMessage.value = null

  // Wir sammeln alle Lösch-Requests
  const requests = selectedIds.value.map(id =>
      axios.delete(`${API_URL}/${id}`)
          .then(() => ({ status: 'fulfilled', id }))
          .catch((err) => ({ status: 'rejected', id, error: err }))
  )

  try {
    // Parallel ausführen und warten bis alle fertig sind
    const results = await Promise.all(requests)

    const deletedIds: number[] = []
    let failCount = 0

    results.forEach((res: any) => {
      if (res.status === 'fulfilled') {
        deletedIds.push(res.id)
      } else {
        failCount++
      }
    })

    // Liste aktualisieren
    departments.value = departments.value.filter(d => !deletedIds.includes(d.AbteilungID))
    selectedIds.value = [] // Auswahl leeren

    // Feedback geben
    if (failCount === 0) {
      successMessage.value = `${deletedIds.length} Abteilungen erfolgreich gelöscht.`
    } else {
      // Gemischtes Ergebnis
      errorMessage.value = `${deletedIds.length} gelöscht. ${failCount} konnten nicht gelöscht werden (da in Verwendung).`
    }

  } catch (e) {
    errorMessage.value = 'Unerwarteter Fehler beim Massenlöschen.'
  } finally {
    isDeletingBulk.value = false
  }
}

onMounted(() => {
  fetchDepartments()
})
</script>

<template>
  <div class="page-container d-flex flex-column align-center">

    <div class="w-100 d-flex justify-start mb-4" style="max-width: 600px;">
      <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-arrow-left"
          @click="goBack"
      >
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4 w-100" max-width="600">
      <v-card-title class="pa-0 mb-4 d-flex justify-space-between align-start">
        <div>
          <h3 class="ma-0">Abteilungen verwalten</h3>
          <div class="text-caption text-medium-emphasis">
            Neue Abteilungen erstellen, umbenennen oder entfernen.
          </div>
        </div>
      </v-card-title>

      <v-card-text class="pa-0">

        <v-alert v-if="errorMessage && !showEditDialog" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = null">
          {{ errorMessage }}
        </v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable @click:close="successMessage = null">
          {{ successMessage }}
        </v-alert>

        <div class="d-flex align-center gap-2 mb-6 pt-2">
          <v-text-field
              v-model="newDeptName"
              label="Name der neuen Abteilung"
              variant="outlined"
              density="comfortable"
              hide-details
              @keyup.enter="addDepartment"
          ></v-text-field>
          <v-btn
              color="primary"
              height="48"
              :loading="isSubmitting"
              :disabled="!newDeptName"
              @click="addDepartment"
          >
            Hinzufügen
          </v-btn>
        </div>

        <v-divider class="mb-2"></v-divider>

        <div v-if="isLoading && departments.length === 0" class="text-center py-4">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <div v-else-if="departments.length > 0" class="d-flex align-center justify-space-between py-2 px-1">
          <div class="d-flex align-center">
            <v-checkbox-btn
                v-model="allSelected"
                color="primary"
                class="mr-2"
            ></v-checkbox-btn>
            <span class="text-caption font-weight-bold">
               {{ selectedIds.length > 0 ? `${selectedIds.length} ausgewählt` : 'Alle auswählen' }}
             </span>
          </div>

          <v-expand-transition>
            <div v-if="selectedIds.length > 0">
              <v-btn
                  color="error"
                  variant="flat"
                  size="small"
                  prepend-icon="mdi-delete"
                  :loading="isDeletingBulk"
                  @click="deleteSelected"
              >
                Löschen ({{ selectedIds.length }})
              </v-btn>
            </div>
          </v-expand-transition>
        </div>

        <v-list v-if="departments.length > 0" density="comfortable" class="bg-transparent pa-0">
          <v-list-item
              v-for="dept in departments"
              :key="dept.AbteilungID"
              class="department-item mb-1 rounded border"
              @click="null"
          >
            <template v-slot:prepend>
              <v-list-item-action start>
                <v-checkbox-btn
                    v-model="selectedIds"
                    :value="dept.AbteilungID"
                    color="primary"
                ></v-checkbox-btn>
              </v-list-item-action>
            </template>

            <v-list-item-title class="font-weight-medium">
              {{ dept.name }}
            </v-list-item-title>

            <template v-slot:append>
              <v-btn
                  icon="mdi-pencil"
                  variant="text"
                  color="blue"
                  density="comfortable"
                  title="Bearbeiten"
                  class="mr-1"
                  @click.stop="openEditDialog(dept)"
              ></v-btn>

              <v-btn
                  icon="mdi-delete"
                  variant="text"
                  color="red-lighten-1"
                  density="comfortable"
                  title="Löschen"
                  @click.stop="deleteDepartment(dept.AbteilungID, dept.name)"
              ></v-btn>
            </template>
          </v-list-item>
        </v-list>

        <div v-if="!isLoading && departments.length === 0" class="text-center text-medium-emphasis mt-4">
          Noch keine Abteilungen angelegt.
        </div>

      </v-card-text>
    </v-card>

    <v-dialog v-model="showEditDialog" max-width="400">
      <v-card>
        <v-card-title class="text-h6">Abteilung umbenennen</v-card-title>
        <v-card-text>
          <v-text-field
              v-model="editDeptName"
              label="Name"
              variant="outlined"
              autofocus
              @keyup.enter="updateDepartment"
          ></v-text-field>
          <div v-if="errorMessage && showEditDialog" class="text-caption text-red mt-1">
            {{ errorMessage }}
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="showEditDialog = false">Abbrechen</v-btn>
          <v-btn color="primary" variant="text" :loading="isUpdating" @click="updateDepartment">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </div>
</template>

<style scoped>
/* Container nimmt volle Höhe und Breite ein, um zu zentrieren */
.page-container {
  padding: 24px;
  width: 100%;
  min-height: 80vh; /* Sorgt dafür, dass es vertikal Luft hat */
}

.gap-2 { gap: 8px; }

.department-item:hover {
  background-color: #fafafa;
}
.border {
  border: 1px solid rgba(0,0,0,0.12);
}
</style>