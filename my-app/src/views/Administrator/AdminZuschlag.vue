<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

// --- State ---
interface Zuschlag {
  ID: number
  faktor: number
  gueltigVon: string
  gueltigBis: string | null
}

const items = ref<Zuschlag[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)

// State für Neu
const newFaktor = ref<string>('')
const newGueltigVon = ref<string>('')
const newGueltigBis = ref<string>('')

// State für Edit
const showEditDialog = ref(false)
const editId = ref<number | null>(null)
const editFaktor = ref<string>('')
const editGueltigVon = ref<string>('')
const editGueltigBis = ref<string>('')
const isUpdating = ref(false)

const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// --- Navigation ---
function goBack() {
  router.push({ name: 'Dashboard' })
}

// --- Helper ---
function formatDate(dateStr: string | null) {
  if (!dateStr) return '∞' // Unendlich
  return new Date(dateStr).toLocaleDateString('de-DE')
}

// --- API ---
const API_URL = import.meta.env.VITE_API_URL + '/api/admin/zuschlaege'

async function fetchItems() {
  isLoading.value = true
  try {
    const response = await axios.get<Zuschlag[]>(API_URL)
    items.value = response.data
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Fehler beim Laden der Zuschläge.'
  } finally {
    isLoading.value = false
  }
}

async function addFaktor() {
  if (!newFaktor.value || !newGueltigVon.value) {
    errorMessage.value = 'Faktor und "Gültig von" sind Pflichtfelder.'
    return
  }

  const val = parseFloat(newFaktor.value.replace(',', '.'))
  if (isNaN(val)) {
    errorMessage.value = 'Bitte eine gültige Zahl eingeben.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const payload = {
      faktor: val,
      gueltigVon: newGueltigVon.value,
      gueltigBis: newGueltigBis.value || null
    }

    const response = await axios.post(API_URL, payload)
    items.value.push(response.data)
    // Neu sortieren
    items.value.sort((a, b) => new Date(b.gueltigVon).getTime() - new Date(a.gueltigVon).getTime())

    // Reset
    newFaktor.value = ''
    newGueltigVon.value = ''
    newGueltigBis.value = ''

    successMessage.value = 'Zuschlag erfolgreich hinzugefügt.'
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Fehler beim Hinzufügen.'
  } finally {
    isSubmitting.value = false
  }
}

function openEditDialog(item: Zuschlag) {
  editId.value = item.ID
  editFaktor.value = item.faktor.toString()
  // Datum für input type="date" muss YYYY-MM-DD sein.
  // Laravel schickt meist YYYY-MM-DD, daher direkt nutzbar.
  editGueltigVon.value = item.gueltigVon
  editGueltigBis.value = item.gueltigBis || ''

  showEditDialog.value = true
  errorMessage.value = null
}

async function updateFaktor() {
  if (!editFaktor.value || !editId.value || !editGueltigVon.value) return

  const val = parseFloat(editFaktor.value.replace(',', '.'))

  isUpdating.value = true
  errorMessage.value = null

  try {
    const payload = {
      faktor: val,
      gueltigVon: editGueltigVon.value,
      gueltigBis: editGueltigBis.value || null
    }

    const response = await axios.put(`${API_URL}/${editId.value}`, payload)

    // Update in Liste
    const index = items.value.findIndex(i => i.ID === editId.value)
    if (index !== -1) {
      items.value[index] = response.data
    }

    successMessage.value = 'Zuschlag aktualisiert.'
    showEditDialog.value = false
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Update fehlgeschlagen.'
  } finally {
    isUpdating.value = false
  }
}

async function deleteFaktor(id: number, val: number) {
  if (!confirm(`Möchtest du den Faktor ${val} wirklich löschen?`)) return

  isLoading.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    await axios.delete(`${API_URL}/${id}`)
    items.value = items.value.filter(i => i.ID !== id)
    successMessage.value = `Faktor ${val} gelöscht.`
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Löschen fehlgeschlagen.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchItems()
})
</script>

<template>
  <div class="page-container d-flex flex-column align-center">
    <div class="w-100 d-flex justify-start mb-4" style="max-width: 800px;">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="goBack">
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4 w-100" max-width="800">
      <v-card-title class="pa-0 mb-4">
        <h3 class="ma-0">Feiertagszuschläge</h3>
        <div class="text-caption text-medium-emphasis">
          Hier bearbeitest du den Feiertagszuschlag, der für die Übungsleiter gelten soll.
        </div>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-alert v-if="errorMessage && !showEditDialog" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = null">{{ errorMessage }}</v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable @click:close="successMessage = null">{{ successMessage }}</v-alert>

        <v-card variant="outlined" class="pa-3 mb-6 bg-grey-lighten-5">
          <div class="text-subtitle-2 mb-2 font-weight-bold">Neuen Zuschlag anlegen</div>
          <v-row dense>
            <v-col cols="12" sm="3">
              <v-text-field
                  v-model="newFaktor"
                  label="Faktor (z.B. 1.5)"
                  type="number"
                  step="0.01"
                  variant="outlined"
                  density="compact"
                  bg-color="white"
                  hide-details
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="3">
              <v-text-field
                  v-model="newGueltigVon"
                  label="Gültig Von"
                  type="date"
                  variant="outlined"
                  density="compact"
                  bg-color="white"
                  hide-details
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="3">
              <v-text-field
                  v-model="newGueltigBis"
                  label="Gültig Bis (opt.)"
                  type="date"
                  variant="outlined"
                  density="compact"
                  bg-color="white"
                  hide-details
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="3">
              <v-btn color="primary" block height="40" :loading="isSubmitting" @click="addFaktor">
                Speichern
              </v-btn>
            </v-col>
          </v-row>
        </v-card>

        <v-divider class="mb-2"></v-divider>

        <div v-if="isLoading && items.length === 0" class="text-center py-4">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <v-table v-else density="comfortable" class="bg-transparent">
          <thead>
          <tr>
            <th class="text-left">Faktor</th>
            <th class="text-left">Zeitraum</th>
            <th class="text-right">Aktionen</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="item in items" :key="item.ID" class="hover-row">
            <td>
              <div class="d-flex align-center">
                <v-icon icon="mdi-percent-outline" color="orange-darken-2" size="small" class="mr-2"></v-icon>
                <span class="font-weight-bold text-h6">{{ item.faktor }}</span>
              </div>
            </td>
            <td>
              <div class="text-body-2">
                {{ formatDate(item.gueltigVon) }} - {{ formatDate(item.gueltigBis) }}
              </div>
            </td>
            <td class="text-right">
              <v-btn icon="mdi-pencil" variant="text" color="blue" density="comfortable" @click="openEditDialog(item)"></v-btn>
              <v-btn icon="mdi-delete" variant="text" color="red" density="comfortable" @click="deleteFaktor(item.ID, item.faktor)"></v-btn>
            </td>
          </tr>
          </tbody>
        </v-table>

        <div v-if="!isLoading && items.length === 0" class="text-center text-medium-emphasis mt-4">
          Keine Zuschläge definiert.
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showEditDialog" max-width="500">
      <v-card>
        <v-card-title>Zuschlag bearbeiten</v-card-title>
        <v-card-text>
          <v-row dense class="mt-2">
            <v-col cols="12">
              <v-text-field
                  v-model="editFaktor"
                  label="Faktor"
                  type="number"
                  step="0.01"
                  variant="outlined"
              ></v-text-field>
            </v-col>
            <v-col cols="6">
              <v-text-field
                  v-model="editGueltigVon"
                  label="Gültig Von"
                  type="date"
                  variant="outlined"
              ></v-text-field>
            </v-col>
            <v-col cols="6">
              <v-text-field
                  v-model="editGueltigBis"
                  label="Gültig Bis"
                  type="date"
                  variant="outlined"
                  clearable
              ></v-text-field>
            </v-col>
          </v-row>

          <div v-if="errorMessage && showEditDialog" class="text-caption text-red mt-1">{{ errorMessage }}</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="showEditDialog = false">Abbrechen</v-btn>
          <v-btn color="primary" variant="text" :loading="isUpdating" @click="updateFaktor">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 24px; width: 100%; min-height: 80vh; }
.hover-row:hover { background-color: #fafafa; }
</style>