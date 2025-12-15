<script setup lang="ts">
import { ref, onMounted, computed } from 'vue' // 'computed' importieren
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_URL = 'http://127.0.0.1:8000/api/abrechnung/meine'

// Interface angepasst an die Controller-Antwort
interface Submission {
  id: number
  zeitraum: string
  quartal_name: string // <--- NEU: Vom Controller hinzugefügt
  stunden: number
  status: string
  status_id: number
  datum_erstellt: string
}

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const selectedQuarter = ref<string | null>(null) // <--- NEU: State für den Filter

// Daten laden
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

// --- FILTER LOGIK ---

// 1. Extrahiere alle eindeutigen Quartals-Namen für das Dropdown
const availableQuarters = computed(() => {
  // Set filtert Duplikate raus
  const quarters = new Set(submissions.value.map(s => s.quartal_name).filter(Boolean))
  // In Array umwandeln und sortieren (Neueste zuerst oder alphabetisch)
  return Array.from(quarters).sort().reverse()
})

// 2. Filter die Liste basierend auf der Auswahl
const filteredSubmissions = computed(() => {
  if (!selectedQuarter.value) {
    return submissions.value // Keine Auswahl -> Alles anzeigen
  }
  return submissions.value.filter(s => s.quartal_name === selectedQuarter.value)
})

// --- HELPER ---

function getStatusColor(statusId: number): string {
  if (statusId === 10) return 'grey'
  if (statusId === 11) return 'blue'
  if (statusId === 21) return 'orange-darken-1'
  if (statusId === 22) return 'green'
  return 'grey-darken-1'
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
              <th class="text-left">Quartal</th> <th class="text-left">Zeitraum</th>
              <th class="text-left">Eingereicht am</th>
              <th class="text-right">Stunden</th>
              <th class="text-center">Status</th>
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
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 900px; /* Etwas breiter gemacht für die zusätzliche Spalte */
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