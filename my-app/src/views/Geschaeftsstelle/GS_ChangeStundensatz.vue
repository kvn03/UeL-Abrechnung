<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()

// --- Typen & Interfaces ---
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
  abteilungen: string
  abteilung_id: number
  aktuellerSatz: number | null
  gueltigSeit: string | null
  // NEU: Historie
  history?: RateHistoryEntry[]
  isLoadingHistory?: boolean
}

// --- State ---
const isLoading = ref(false)
const isSaving = ref(false)
const users = ref<UserWithRate[]>([])

// Filter State
const search = ref('')
const filterDept = ref<number | null>(null)
const filterUser = ref<number | null>(null)

// NEU: Expanded State
const expanded = ref<string[]>([])

const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Dialog State
const showDialog = ref(false)
const editForm = ref<VForm | null>(null)
const selectedUser = ref<UserWithRate | null>(null)
const newRate = ref<number | string>('')
const validFrom = ref<string>('')

// --- API Endpoints ---
const API_ALL_USERS = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/mitarbeiter'
const API_UPDATE_RATE = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/stundensatz'
const API_HISTORY = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/stundensatz-historie' // <--- NEU

// --- Initialisierung ---
onMounted(async () => {
  await loadAllUsers()
})

// --- Data Loading ---
async function loadAllUsers() {
  isLoading.value = true
  errorMessage.value = null
  expanded.value = [] // Reset expanded
  try {
    const response = await axios.get(API_ALL_USERS)
    users.value = response.data
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Konnte Mitarbeiterliste nicht laden.'
  } finally {
    isLoading.value = false
  }
}

// --- NEU: Historie laden ---
async function loadHistoryForUser(item: any) {
  // Achtung: item ist hier das Row-Objekt.
  // Wir müssen das echte Objekt im Array finden, um Reactivity zu nutzen.
  // Da ein User mehrfach vorkommen kann, suchen wir nach ID UND Abteilung!
  const targetUser = users.value.find(u => u.id === item.id && u.abteilung_id === item.abteilung_id)

  if (!targetUser) return
  if (targetUser.history) return // Schon geladen

  targetUser.isLoadingHistory = true
  try {
    const response = await axios.get(API_HISTORY, {
      params: {
        user_id: targetUser.id,
        abteilung_id: targetUser.abteilung_id
      }
    })
    targetUser.history = response.data
  } catch (e) {
    console.error("Fehler History laden", e)
  } finally {
    targetUser.isLoadingHistory = false
  }
}

// --- Computed: Filter-Optionen ---
const departmentOptions = computed(() => {
  const map = new Map<number, string>()
  users.value.forEach(u => {
    if (u.abteilung_id && u.abteilungen) map.set(u.abteilung_id, u.abteilungen)
  })
  return Array.from(map, ([id, name]) => ({ title: name, value: id })).sort((a, b) => a.title.localeCompare(b.title))
})

const userOptions = computed(() => {
  const map = new Map<number, string>()
  users.value.forEach(u => { map.set(u.id, `${u.vorname} ${u.name}`) })
  return Array.from(map, ([id, name]) => ({ title: name, value: id })).sort((a, b) => a.title.localeCompare(b.title))
})

const filteredUsers = computed(() => {
  return users.value.filter(item => {
    if (filterDept.value !== null && item.abteilung_id !== filterDept.value) return false
    if (filterUser.value !== null && item.id !== filterUser.value) return false
    return true
  })
})

// --- Edit Dialog ---
function openEditDialog(user: UserWithRate) {
  selectedUser.value = user
  newRate.value = user.aktuellerSatz || ''

  // ÄNDERUNG: Standardmäßig "Heute" statt "Morgen", damit man schneller auswählen kann.
  // Man kann jetzt auch vergangene Daten im Date-Picker wählen.
  const today = new Date()
  validFrom.value = today.toISOString().split('T')[0]

  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  selectedUser.value = null
  newRate.value = ''
  errorMessage.value = null
}

async function saveRate() {
  const { valid } = await editForm.value?.validate() || { valid: false }
  if (!valid || !selectedUser.value) return

  isSaving.value = true
  errorMessage.value = null

  const payload = {
    user_id: selectedUser.value.id,
    abteilung_id: selectedUser.value.abteilung_id,
    satz: parseFloat(newRate.value.toString()),
    gueltig_ab: validFrom.value
  }

  try {
    await axios.post(API_UPDATE_RATE, payload)
    successMessage.value = `Stundensatz aktualisiert.`
    setTimeout(() => successMessage.value = null, 3000)
    closeDialog()
    await loadAllUsers()
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
      <div class="mb-4">
        <h3 class="ma-0">Alle Stundensätze verwalten (Geschäftsstelle)</h3>
        <div class="text-caption text-medium-emphasis">Übersicht aller Übungsleiter nach Abteilung</div>
      </div>

      <v-row dense class="mb-2 align-center">
        <v-col cols="12" md="4">
          <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Suche (Text)" variant="outlined" density="compact" hide-details clearable></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-autocomplete v-model="filterDept" :items="departmentOptions" label="Nach Abteilung filtern" variant="outlined" density="compact" hide-details clearable placeholder="Alle Abteilungen"></v-autocomplete>
        </v-col>
        <v-col cols="12" md="4">
          <v-autocomplete v-model="filterUser" :items="userOptions" label="Nach Mitarbeiter filtern" variant="outlined" density="compact" hide-details clearable placeholder="Alle Mitarbeiter"></v-autocomplete>
        </v-col>
      </v-row>

      <v-card-text class="pa-0 mt-4">
        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable>{{ errorMessage }}</v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable>{{ successMessage }}</v-alert>

        <v-data-table
            v-model:expanded="expanded"
            :headers="[
            { title: 'Name', key: 'name' },
            { title: 'Abteilung', key: 'abteilungen' },
            { title: 'Aktueller Satz', key: 'aktuellerSatz', align: 'end' },
            { title: 'Gültig seit', key: 'gueltigSeit', align: 'end' },
            { title: 'Aktion', key: 'actions', align: 'end', sortable: false },
            { title: '', key: 'data-table-expand' } // Expliziter Header für Expand-Icon (optional)
          ]"
            :items="filteredUsers"
            :search="search"
            :loading="isLoading"
            :item-value="(item) => `${item.id}_${item.abteilung_id}`"
            show-expand
            hover
            density="comfortable"
            @update:expanded="(newVal) => {
               if (newVal.length > 0) {
                 // newVal enthält Strings wie '5_12' (UserId_AbteilungId)
                 // Wir suchen das passende Objekt in der filtered Liste
                 const lastKey = newVal[newVal.length - 1];
                 const item = filteredUsers.find(u => `${u.id}_${u.abteilung_id}` === lastKey);
                 if(item) loadHistoryForUser(item);
               }
            }"
        >
          <template v-slot:item.name="{ item }">
            <div class="font-weight-medium">{{ item.name }}, {{ item.vorname }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
          </template>

          <template v-slot:item.abteilungen="{ item }">
            <v-chip size="small" color="blue-grey-lighten-4" class="font-weight-medium">
              {{ item.abteilungen }}
            </v-chip>
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
                <div class="text-subtitle-2 mb-2 text-medium-emphasis">
                  Historie der Stundensätze für <b>{{ item.abteilungen }}</b>
                </div>

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
            <div class="pa-4 text-center text-medium-emphasis">Keine Einträge gefunden.</div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="500px">
      <v-card v-if="selectedUser">
        <v-card-title class="bg-primary text-white">Stundensatz ändern</v-card-title>
        <v-card-subtitle class="pt-3">
          Mitarbeiter: <b>{{ selectedUser.vorname }} {{ selectedUser.name }}</b> <br>
          Abteilung: <b>{{ selectedUser.abteilungen }}</b>
        </v-card-subtitle>

        <v-card-text class="pt-4">
          <v-form ref="editForm" @submit.prevent="saveRate">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4 text-caption">
              Ein neuer Eintrag wird erstellt. Der vorherige Satz wird (falls vorhanden) automatisch zum Tag vor dem hier gewählten Datum beendet.
              <br><strong>Hinweis:</strong> Rückwirkende Änderungen können bereits erstellte Abrechnungen beeinflussen!
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
          <v-btn color="primary" variant="elevated" @click="saveRate" :loading="isSaving">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 1200px; margin: 0 auto; }
</style>