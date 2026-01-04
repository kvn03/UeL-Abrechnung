<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()

// --- Typen & Interfaces ---
interface Department {
  id: number
  name: string
}

// Typ für einen historischen Eintrag
interface RateHistoryEntry {
  satz: number
  gueltigVon: string
  gueltigBis: string | null
}

interface UserWithRate {
  id: number
  name: string
  vorname: string
  email: string
  aktuellerSatz: number | null
  gueltigSeit: string | null
  // NEU: Hier speichern wir die Historie, sobald sie geladen wurde
  history?: RateHistoryEntry[]
  isLoadingHistory?: boolean
}

// --- State ---
const isLoading = ref(false)
const isSaving = ref(false)
const departments = ref<Department[]>([])
const selectedDepartment = ref<number | null>(null)
const users = ref<UserWithRate[]>([])
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// NEU: State für aufgeklappte Zeilen
const expanded = ref<string[]>([])

// Dialog State
const showDialog = ref(false)
const editForm = ref<VForm | null>(null)
const selectedUser = ref<UserWithRate | null>(null)
const newRate = ref<number | string>('')
const validFrom = ref<string>('')

// --- API Endpoints ---
const API_DEPARTMENTS = 'http://127.0.0.1:8000/api/meine-al-abteilungen'
const API_USERS = 'http://127.0.0.1:8000/api/abteilungsleiter/mitarbeiter'
const API_UPDATE_RATE = 'http://127.0.0.1:8000/api/abteilungsleiter/stundensatz'
// NEU: Endpoint für Historie (Beispiel)
const API_HISTORY = 'http://127.0.0.1:8000/api/abteilungsleiter/stundensatz-historie'

// --- Initialisierung ---
onMounted(async () => {
  await loadDepartments()
})

watch(selectedDepartment, async (newVal) => {
  if (newVal) {
    await loadUsers(newVal)
  } else {
    users.value = []
  }
})

// --- Data Loading ---
async function loadDepartments() {
  try {
    const response = await axios.get(API_DEPARTMENTS)
    departments.value = response.data
    if (departments.value.length > 0) {
      selectedDepartment.value = departments.value[0].id
    }
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Konnte Abteilungen nicht laden.'
  }
}

async function loadUsers(deptId: number) {
  isLoading.value = true
  errorMessage.value = null
  expanded.value = [] // Alle zugeklappten Zeilen resetten
  try {
    const response = await axios.get(`${API_USERS}?abteilung_id=${deptId}`)
    users.value = response.data
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Konnte Mitarbeiterliste nicht laden.'
  } finally {
    isLoading.value = false
  }
}

// NEU: Historie laden, wenn Zeile aufgeklappt wird
async function loadHistoryForUser(item: any) {
  // item ist hier das Objekt aus der Row, aber Vorsicht bei Reactivity:
  // Wir suchen das Objekt in unserem users-Array
  const user = users.value.find(u => u.id === item.id)
  if (!user) return

  // Wenn wir die Historie schon haben, nicht nochmal laden
  if (user.history) return

  user.isLoadingHistory = true
  try {
    // Backend Call: Wir brauchen die Historie für diesen User in dieser Abteilung
    // URL anpassen je nach deinem Backend
    const response = await axios.get(`${API_HISTORY}`, {
      params: {
        user_id: user.id,
        abteilung_id: selectedDepartment.value
      }
    })
    user.history = response.data
  } catch (e) {
    console.error("Fehler beim Laden der Historie", e)
  } finally {
    user.isLoadingHistory = false
  }
}

// --- Edit Dialog ---
function openEditDialog(user: UserWithRate) {
  selectedUser.value = user
  newRate.value = user.aktuellerSatz || ''

  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  validFrom.value = tomorrow.toISOString().split('T')[0]

  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  selectedUser.value = null
  newRate.value = ''
  errorMessage.value = null
}

// --- Speichern ---
async function saveRate() {
  const { valid } = await editForm.value?.validate() || { valid: false }
  if (!valid || !selectedUser.value || !selectedDepartment.value) return

  isSaving.value = true
  errorMessage.value = null

  const payload = {
    user_id: selectedUser.value.id,
    abteilung_id: selectedDepartment.value,
    satz: parseFloat(newRate.value.toString()),
    gueltig_ab: validFrom.value
  }

  try {
    await axios.post(API_UPDATE_RATE, payload)

    successMessage.value = `Stundensatz aktualisiert.`
    setTimeout(() => successMessage.value = null, 3000)

    closeDialog()
    await loadUsers(selectedDepartment.value)

  } catch (error: any) {
    console.error(error)
    errorMessage.value = error.response?.data?.message || 'Fehler beim Speichern.'
  } finally {
    isSaving.value = false
  }
}

// --- Helper ---
function formatCurrency(value: number | null) {
  if (value === null || value === undefined) return '-'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value)
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE')
}

function goBack() {
  router.push({ name: 'Dashboard' })
}
</script>

<template>
  <div class="page">
    <div class="d-flex justify-start mb-4">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="goBack">
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4">
      <v-card-title class="d-flex justify-space-between align-center mb-4">
        <h3 class="ma-0">Stundensätze verwalten</h3>
      </v-card-title>

      <v-card-text>
        <v-select
            v-if="departments.length > 1"
            v-model="selectedDepartment"
            :items="departments"
            item-title="name"
            item-value="id"
            label="Abteilung wählen"
            variant="outlined"
            density="comfortable"
            class="mb-4"
            style="max-width: 400px;"
        ></v-select>

        <div v-else-if="departments.length === 1" class="text-h6 text-primary mb-4">
          Abteilung: {{ departments[0].name }}
        </div>

        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable>{{ errorMessage }}</v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable>{{ successMessage }}</v-alert>

        <v-data-table
            v-model:expanded="expanded"
            :headers="[
              { title: 'Name', key: 'name' },
              { title: 'Aktueller Satz', key: 'aktuellerSatz', align: 'end' },
              { title: 'Gültig seit', key: 'gueltigSeit', align: 'end' },
              { title: 'Aktion', key: 'actions', align: 'end', sortable: false },
              // Leer-Header für den Pfeil wird automatisch generiert durch show-expand
            ]"
            :items="users"
            :loading="isLoading"
            item-value="id"
            show-expand
            hover
            density="comfortable"
            @update:expanded="(newVal) => {
              // Wenn eine Zeile aufgeklappt wird, laden wir Daten nach
              if (newVal.length > 0) {
                 // Die ID des zuletzt aufgeklappten Items finden
                 const lastId = newVal[newVal.length - 1];
                 const item = users.find(u => u.id === lastId);
                 if(item) loadHistoryForUser(item);
              }
            }"
        >
          <template v-slot:item.name="{ item }">
            <div class="font-weight-medium">{{ item.name }}, {{ item.vorname }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
          </template>

          <template v-slot:item.aktuellerSatz="{ item }">
            <v-chip :color="item.aktuellerSatz ? 'green' : 'grey'" variant="flat" size="small" class="font-weight-bold">
              {{ formatCurrency(item.aktuellerSatz) }}
            </v-chip>
          </template>

          <template v-slot:item.gueltigSeit="{ item }">
            {{ formatDate(item.gueltigSeit) }}
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
                color="primary"
                variant="text"
                icon="mdi-pencil"
                @click.stop="openEditDialog(item)"
                title="Satz ändern"
            ></v-btn>
          </template>

          <template v-slot:expanded-row="{ columns, item }">
            <tr>
              <td :colspan="columns.length" class="bg-grey-lighten-5 pa-4">
                <div class="text-subtitle-2 mb-2 text-medium-emphasis">Historie der Stundensätze</div>

                <div v-if="item.isLoadingHistory" class="d-flex align-center py-2">
                  <v-progress-circular indeterminate size="20" width="2" color="primary" class="mr-2"></v-progress-circular>
                  <span class="text-caption">Lade Historie...</span>
                </div>

                <div v-else-if="!item.history || item.history.length === 0" class="text-caption font-italic text-medium-emphasis">
                  Keine historischen Einträge gefunden.
                </div>

                <v-table v-else density="compact" class="bg-transparent" style="max-width: 600px;">
                  <thead>
                  <tr>
                    <th class="text-left">Zeitraum</th>
                    <th class="text-right">Satz</th>
                    <th class="text-left pl-4">Status</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(hist, i) in item.history" :key="i">
                    <td>
                      {{ formatDate(hist.gueltigVon) }}
                      -
                      {{ hist.gueltigBis ? formatDate(hist.gueltigBis) : 'heute' }}
                    </td>
                    <td class="text-right font-weight-bold">
                      {{ formatCurrency(hist.satz) }}
                    </td>
                    <td class="pl-4">
                      <v-chip size="x-small" :color="hist.gueltigBis ? 'grey' : 'green'" variant="tonal">
                        {{ hist.gueltigBis ? 'Archiviert' : 'Aktiv' }}
                      </v-chip>
                    </td>
                  </tr>
                  </tbody>
                </v-table>
              </td>
            </tr>
          </template>

          <template v-slot:no-data>
            <div class="pa-4 text-center text-medium-emphasis">Keine Mitarbeiter in dieser Abteilung gefunden.</div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="500px">
      <v-card v-if="selectedUser">
        <v-card-title class="bg-primary text-white">
          Stundensatz ändern
        </v-card-title>
        <v-card-subtitle class="pt-3">
          Mitarbeiter: <b>{{ selectedUser.vorname }} {{ selectedUser.name }}</b>
        </v-card-subtitle>

        <v-card-text class="pt-4">
          <v-form ref="editForm" @submit.prevent="saveRate">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4 text-caption">
              Der aktuelle Satz ({{ formatCurrency(selectedUser.aktuellerSatz) }}) wird zum Tag vor dem "Gültig ab"-Datum beendet.
            </v-alert>

            <v-text-field
                v-model="newRate"
                label="Neuer Stundensatz (€)"
                type="number"
                step="0.01"
                variant="outlined"
                suffix="€"
                :rules="[v => !!v || 'Pflichtfeld', v => v > 0 || 'Muss > 0 sein']"
            ></v-text-field>

            <v-text-field
                v-model="validFrom"
                label="Gültig ab"
                type="date"
                variant="outlined"
                :rules="[v => !!v || 'Pflichtfeld']"
            ></v-text-field>
          </v-form>
        </v-card-text>

        <v-card-actions class="justify-end pa-4 pt-0">
          <v-btn variant="text" @click="closeDialog">Abbrechen</v-btn>
          <v-btn
              color="primary"
              variant="elevated"
              @click="saveRate"
              :loading="isSaving"
          >
            Speichern
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 1000px; margin: 0 auto; }
</style>