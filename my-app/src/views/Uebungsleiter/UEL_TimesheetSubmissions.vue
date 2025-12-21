<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_URL = 'http://127.0.0.1:8000/api/abrechnung/meine'

// --- INTERFACES ---

interface Submission {
  id: number
  zeitraum: string
  quartal_name: string
  stunden: number
  status: string
  status_id: number
  datum_erstellt: string
}

interface HistoryEntry {
  type: 'audit' | 'status'
  date: string
  title: string
  details: string
  kommentar: string | null
}

// NEU: Interface für Abrechnungs-Log
interface AbrechnungLogEntry {
  date: string
  title: string
  kommentar: string | null
}

interface DetailEntry {
  id: number
  datum: string
  start: string
  ende: string
  dauer: number
  kurs: string
  history: HistoryEntry[]
}

interface AbrechnungDetails {
  abrechnung_id: number
  quartal: string
  abrechnung_history: AbrechnungLogEntry[] // NEU
  eintraege: DetailEntry[]
}

// --- STATE ---

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const selectedQuarter = ref<string | null>(null)

// Details Dialog
const showDetailDialog = ref(false)
const isLoadingDetails = ref(false)
const selectedAbrechnungDetails = ref<AbrechnungDetails | null>(null)

// --- API ---

async function fetchSubmissions() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'Die Abrechnungen konnten nicht geladen werden.'
  } finally {
    isLoading.value = false
  }
}

async function openDetails(abrechnungId: number) {
  showDetailDialog.value = true
  isLoadingDetails.value = true
  selectedAbrechnungDetails.value = null

  try {
    const response = await axios.get(`${API_URL}/${abrechnungId}`)
    selectedAbrechnungDetails.value = response.data
  } catch (error) {
    console.error(error)
  } finally {
    isLoadingDetails.value = false
  }
}

// --- FILTER ---

const availableQuarters = computed(() => {
  const quarters = new Set(submissions.value.map(s => s.quartal_name).filter(Boolean))
  return Array.from(quarters).sort().reverse()
})

const filteredSubmissions = computed(() => {
  if (!selectedQuarter.value) {
    return submissions.value
  }
  return submissions.value.filter(s => s.quartal_name === selectedQuarter.value)
})

// --- HELPER ---

function getStatusColor(statusId: number): string {
  if (statusId === 10) return 'grey'
  if (statusId === 11) return 'blue'
  if (statusId === 20) return 'indigo'
  if (statusId === 21) return 'orange-darken-1'
  if (statusId === 22) return 'green'
  if (statusId === 23) return 'teal'
  if (statusId === 24) return 'red' // Abgebrochen
  return 'grey-darken-1'
}

function formatDateDisplay(dateString: string) {
  if(!dateString) return ''
  return new Date(dateString).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute:'2-digit'
  })
}

onMounted(() => {
  fetchSubmissions()
})
</script>

<template>
  <div class="page">
    <div class="d-flex justify-start mb-4">
      <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-arrow-left"
          @click="goBack"
      >
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4">
      <v-card-title class="pa-0 mb-4 d-flex align-center justify-space-between">
        <h3 class="ma-0">Meine Abrechnungen</h3>
        <v-btn
            size="small"
            variant="text"
            color="primary"
            :loading="isLoading"
            @click="fetchSubmissions"
        >
          Aktualisieren
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2"></v-progress-circular>
          Lade Historie ...
        </div>

        <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="submissions.length > 0">
          <v-row class="mb-2" dense>
            <v-col cols="12" sm="6" md="4">
              <v-select
                  v-model="selectedQuarter"
                  :items="availableQuarters"
                  label="Nach Quartal filtern"
                  density="compact"
                  variant="outlined"
                  hide-details
                  clearable
                  placeholder="Alle Quartale anzeigen"
              ></v-select>
            </v-col>
            <v-col cols="12" sm="6" md="8" class="d-flex align-center justify-end">
              <span class="text-caption text-medium-emphasis" v-if="selectedQuarter">
                Zeige {{ filteredSubmissions.length }} von {{ submissions.length }} Einträgen
              </span>
            </v-col>
          </v-row>

          <v-table density="comfortable" hover>
            <thead>
            <tr>
              <th class="text-left">Quartal</th>
              <th class="text-left">Zeitraum</th>
              <th class="text-left">Eingereicht am</th>
              <th class="text-right">Stunden</th>
              <th class="text-center">Status</th>
              <th class="text-right">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in filteredSubmissions" :key="item.id">
              <td class="font-weight-bold text-primary">{{ item.quartal_name }}</td>
              <td class="text-medium-emphasis">{{ item.zeitraum }}</td>
              <td class="text-medium-emphasis">{{ item.datum_erstellt }}</td>
              <td class="text-right">{{ item.stunden.toLocaleString('de-DE') }} Std.</td>
              <td class="text-center">
                <v-chip
                    :color="getStatusColor(item.status_id)"
                    size="small"
                    variant="flat"
                    class="font-weight-bold text-white"
                >
                  {{ item.status }}
                </v-chip>
              </td>
              <td class="text-right">
                <v-btn
                    icon="mdi-eye-outline"
                    variant="text"
                    color="grey-darken-1"
                    density="comfortable"
                    @click="openDetails(item.id)"
                    title="Details ansehen"
                ></v-btn>
              </td>
            </tr>
            </tbody>
          </v-table>

          <div v-if="filteredSubmissions.length === 0" class="text-center py-4 text-medium-emphasis">
            Keine Abrechnungen für das ausgewählte Quartal gefunden.
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-file-document-outline" size="large" class="mb-2 opacity-50"></v-icon>
          Du hast noch keine Abrechnungen eingereicht.
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDetailDialog" max-width="900px" scrollable>
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center bg-grey-lighten-4 py-3">
          <span>
            Details:
            <span v-if="selectedAbrechnungDetails" class="text-primary">
              {{ selectedAbrechnungDetails.quartal }}
            </span>
          </span>
          <v-btn icon="mdi-close" variant="text" @click="showDetailDialog = false"></v-btn>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text style="height: 600px;" class="pa-0">
          <div v-if="isLoadingDetails" class="d-flex justify-center align-center h-100">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
          </div>

          <div v-else-if="selectedAbrechnungDetails" class="pa-4">

            <div class="mb-6">
              <h3 class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center">
                <v-icon icon="mdi-clipboard-list-outline" start color="primary"></v-icon>
                Statusverlauf der Abrechnung
              </h3>

              <v-card border flat class="bg-grey-lighten-5 pa-2">
                <div v-if="selectedAbrechnungDetails.abrechnung_history.length === 0" class="text-caption text-medium-emphasis">
                  Keine Historie verfügbar.
                </div>

                <v-timeline v-else density="compact" align="start" side="end" truncate-line="start" class="my-0">
                  <v-timeline-item
                      v-for="(hist, idx) in selectedAbrechnungDetails.abrechnung_history"
                      :key="idx"
                      dot-color="primary"
                      size="x-small"
                  >
                    <div class="d-flex flex-column">
                      <div class="d-flex justify-space-between align-center">
                        <strong class="text-body-2">{{ hist.title }}</strong>
                        <span class="text-caption text-grey ml-2">{{ formatDateDisplay(hist.date) }}</span>
                      </div>
                      <div v-if="hist.kommentar" class="text-caption text-medium-emphasis mt-1">
                        {{ hist.kommentar }}
                      </div>
                    </div>
                  </v-timeline-item>
                </v-timeline>
              </v-card>
            </div>

            <v-divider class="mb-6"></v-divider>

            <div>
              <h3 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center">
                <v-icon icon="mdi-clock-time-four-outline" start color="primary"></v-icon>
                Enthaltene Stundeneinträge
                <v-chip size="small" class="ml-2" variant="tonal" color="primary">
                  {{ selectedAbrechnungDetails.eintraege.length }} Einträge
                </v-chip>
              </h3>

              <v-expansion-panels variant="accordion">
                <v-expansion-panel
                    v-for="entry in selectedAbrechnungDetails.eintraege"
                    :key="entry.id"
                >
                  <v-expansion-panel-title>
                    <v-row no-gutters align="center">
                      <v-col cols="3" class="font-weight-bold">{{ entry.datum }}</v-col>
                      <v-col cols="4">{{ entry.kurs }}</v-col>
                      <v-col cols="3" class="text-medium-emphasis text-caption">
                        {{ entry.start }} - {{ entry.ende }} Uhr
                      </v-col>
                      <v-col cols="2" class="text-right pr-4">
                        <strong>{{ entry.dauer }} Std.</strong>
                      </v-col>
                    </v-row>
                  </v-expansion-panel-title>

                  <v-expansion-panel-text>
                    <div v-if="entry.history.length === 0" class="text-center text-medium-emphasis py-4">
                      Keine Änderungen oder Kommentare vorhanden.
                    </div>

                    <v-timeline v-else density="compact" align="start" side="end" class="mt-2">
                      <v-timeline-item
                          v-for="(log, i) in entry.history"
                          :key="i"
                          :dot-color="log.type === 'status' ? 'blue' : 'orange'"
                          size="x-small"
                      >
                        <div class="d-flex flex-column">
                          <div class="d-flex justify-space-between">
                            <strong :class="log.type === 'status' ? 'text-blue' : 'text-orange-darken-2'">
                              {{ log.title }}
                            </strong>
                            <span class="text-caption text-disabled">{{ formatDateDisplay(log.date) }}</span>
                          </div>

                          <div v-if="log.details" class="text-caption font-italic my-1">
                            {{ log.details }}
                          </div>

                          <div v-if="log.kommentar" class="bg-grey-lighten-4 pa-2 rounded mt-1 text-body-2">
                            <v-icon icon="mdi-comment-text-outline" size="small" class="mr-1"></v-icon>
                            {{ log.kommentar }}
                          </div>
                        </div>
                      </v-timeline-item>
                    </v-timeline>
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>
            </div>

          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 900px;
  margin: 0 auto;
}

.placeholder {
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: rgba(0, 0, 0, 0.6);
  font-size: 1rem;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.02);
  margin-top: 8px;
  padding: 16px;
  text-align: center;
}
</style>