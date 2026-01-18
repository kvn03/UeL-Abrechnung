<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_URL = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/abrechnungen-historie'

// --- Interfaces ---
interface HistoryEntry {
  date: string
  status: string
  user: string
  kommentar: string | null
}

// WICHTIG: isFeiertag hinzugefügt
interface DetailEntry {
  datum: string
  dauer: number
  kurs: string
  betrag: number
  isFeiertag?: boolean
}

interface HistoryItem {
  AbrechnungID: number
  mitarbeiterName: string
  abteilung: string
  zeitraum: string
  quartal: string
  stunden: number
  gesamtBetrag: number
  status: string
  status_id: number
  details: DetailEntry[]
  history: HistoryEntry[]
}

// --- State ---
const currentYear = new Date().getFullYear()
const monthIndex = new Date().getMonth()
const currentQuarter = monthIndex <= 2 ? 'Q1' : monthIndex <= 5 ? 'Q2' : monthIndex <= 8 ? 'Q3' : 'Q4'

const selectedYear = ref<number>(currentYear)
const selectedQuarter = ref<string>(currentQuarter)

const years = computed(() => {
  const list: number[] = []
  for (let y = currentYear; y >= currentYear - 4; y--) {
    list.push(y)
  }
  return list
})

const quarters = [
  { value: 'Q1', label: 'Q1 (Jan–Mär)' },
  { value: 'Q2', label: 'Q2 (Apr–Jun)' },
  { value: 'Q3', label: 'Q3 (Jul–Sep)' },
  { value: 'Q4', label: 'Q4 (Okt–Dez)' },
]

const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const items = ref<HistoryItem[]>([])

// Dialog State
const showDialog = ref(false)
const selectedItem = ref<HistoryItem | null>(null)

// --- API ---
async function fetchHistory() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const params = {
      year: selectedYear.value,
      quarter: selectedQuarter.value,
    }
    const response = await axios.get<HistoryItem[]>(API_URL, { params })
    items.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Historie.'
  } finally {
    isLoading.value = false
  }
}

// --- Actions ---
function openDetails(item: HistoryItem) {
  selectedItem.value = item
  showDialog.value = true
}

// --- Helper ---
function formatCurrency(val: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('de-DE')
}

function getStatusColor(id: number) {
  if (id === 23) return 'green' // Bezahlt
  if (id === 22) return 'orange-darken-1' // Warten auf Zahlung
  if (id === 21) return 'blue' // Genehmigt AL
  if (id === 12 || id === 24) return 'red' // Abgelehnt
  return 'grey'
}

onMounted(() => {
  fetchHistory()
})
</script>

<template>
  <div class="page">
    <div class="d-flex justify-start mb-4">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="goBack">
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4">
      <v-card-title class="pa-0 mb-4 d-flex flex-column flex-sm-row align-sm-center justify-space-between">
        <div>
          <h3 class="ma-0">Archiv (Geschäftsstelle)</h3>
          <div class="text-body-2 text-medium-emphasis mt-1">
            Vollständiges Archiv aller Abrechnungen (nur lesend).
          </div>
        </div>

        <div class="filters d-flex flex-wrap mt-3 mt-sm-0">
          <v-select
              v-model="selectedYear"
              :items="years"
              label="Jahr"
              density="compact"
              variant="outlined"
              class="mr-2"
              style="max-width: 100px"
              hide-details
              @update:model-value="fetchHistory"
          />
          <v-select
              v-model="selectedQuarter"
              :items="quarters"
              item-title="label"
              item-value="value"
              label="Quartal"
              density="compact"
              variant="outlined"
              style="max-width: 160px"
              hide-details
              @update:model-value="fetchHistory"
          />
        </div>
      </v-card-title>

      <v-card-text class="pa-0 mt-4">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2" />
          Lade Archiv ...
        </div>

        <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="items.length > 0">
          <v-table density="comfortable" hover>
            <thead>
            <tr>
              <th class="text-left">ID</th>
              <th class="text-left">Mitarbeiter</th>
              <th class="text-left">Abteilung</th>
              <th class="text-right">Gesamt</th>
              <th class="text-center">Status</th>
              <th class="text-right">Details</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in items" :key="item.AbrechnungID">
              <td class="text-medium-emphasis">#{{ item.AbrechnungID }}</td>
              <td class="font-weight-bold">{{ item.mitarbeiterName }}</td>
              <td>
                {{ item.abteilung }} <br>
                <span class="text-caption text-medium-emphasis">{{ item.quartal }}</span>
              </td>
              <td class="text-right">
                <div>{{ item.stunden.toLocaleString('de-DE') }} Std.</div>
                <div class="text-caption font-weight-bold text-medium-emphasis">
                  {{ formatCurrency(item.gesamtBetrag) }}
                </div>
              </td>
              <td class="text-center">
                <v-chip :color="getStatusColor(item.status_id)" size="small" variant="flat" class="text-white font-weight-medium">
                  {{ item.status }}
                </v-chip>
              </td>
              <td class="text-right">
                <v-btn icon="mdi-eye" variant="text" color="primary" density="comfortable" @click="openDetails(item)"></v-btn>
              </td>
            </tr>
            </tbody>
          </v-table>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-archive-off" size="large" color="grey" class="mb-2" />
          Keine Einträge im Archiv gefunden.
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="700px" scrollable>
      <v-card v-if="selectedItem">
        <v-card-title class="bg-grey-lighten-4 d-flex justify-space-between align-center">
          <span>Abrechnung #{{ selectedItem.AbrechnungID }} Details</span>
          <v-chip :color="getStatusColor(selectedItem.status_id)" size="small" variant="flat">{{ selectedItem.status }}</v-chip>
        </v-card-title>

        <v-card-text style="max-height: 600px;">

          <v-row class="mb-4 mt-2">
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Mitarbeiter</div>
              <div class="text-body-1">{{ selectedItem.mitarbeiterName }}</div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Abteilung / Quartal</div>
              <div class="text-body-1">{{ selectedItem.abteilung }} ({{ selectedItem.quartal }})</div>
            </v-col>
            <v-col cols="12">
              <div class="text-caption text-medium-emphasis">Zeitraum</div>
              <div>{{ selectedItem.zeitraum }}</div>
            </v-col>
          </v-row>

          <v-divider class="mb-4"></v-divider>

          <div class="text-subtitle-2 mb-2">Enthaltene Stunden</div>
          <v-table density="compact" class="border rounded mb-4">
            <thead>
            <tr>
              <th class="text-left">Datum</th>
              <th class="text-left">Kurs</th>
              <th class="text-right">Std.</th>
              <th class="text-right">Betrag</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="(d, i) in selectedItem.details"
                :key="i"
                :class="{ 'bg-orange-lighten-5': d.isFeiertag }"
            >
              <td>
                {{ formatDate(d.datum) }}
                <v-icon v-if="d.isFeiertag" icon="mdi-party-popper" color="orange-darken-2" size="small" class="ml-1" title="Feiertag" />
              </td>

              <td>
                {{ d.kurs }}
                <span v-if="d.isFeiertag" class="text-caption text-orange-darken-2 font-weight-bold ml-1">
                    (Feiertag)
                  </span>
              </td>

              <td class="text-right">{{ d.dauer.toLocaleString('de-DE') }}</td>

              <td class="text-right" :class="{ 'text-orange-darken-2 font-weight-bold': d.isFeiertag }">
                {{ formatCurrency(d.betrag) }}
              </td>
            </tr>

            <tr class="bg-grey-lighten-5 font-weight-bold">
              <td colspan="2">Summe</td>
              <td class="text-right">{{ selectedItem.stunden.toLocaleString('de-DE') }}</td>
              <td class="text-right">{{ formatCurrency(selectedItem.gesamtBetrag) }}</td>
            </tr>
            </tbody>
          </v-table>

          <div class="text-subtitle-2 mb-2">Verlauf</div>
          <v-timeline density="compact" align="start" side="end" class="text-caption">
            <v-timeline-item
                v-for="(log, idx) in selectedItem.history"
                :key="idx"
                dot-color="grey"
                size="x-small"
            >
              <div class="d-flex justify-space-between">
                <strong>{{ log.status }}</strong>
                <span class="text-grey">{{ log.date }}</span>
              </div>
              <div>{{ log.user }}</div>
              <div v-if="log.kommentar" class="font-italic text-grey-darken-1 mt-1">"{{ log.kommentar }}"</div>
            </v-timeline-item>
          </v-timeline>

        </v-card-text>

        <v-card-actions class="justify-end border-top">
          <v-btn color="primary" variant="text" @click="showDialog = false">Schließen</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 1000px; margin: 0 auto; }
.placeholder { min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.02); border-radius: 8px; margin-top: 8px; }
.border-top { border-top: 1px solid rgba(0,0,0,0.12); }
.border { border: 1px solid rgba(0,0,0,0.12); }

/* Wichtig für die Hintergrundfarbe */
.bg-orange-lighten-5 { background-color: #FFF3E0 !important; }
</style>