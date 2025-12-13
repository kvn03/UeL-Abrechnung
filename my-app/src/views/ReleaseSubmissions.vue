<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_URL = 'http://127.0.0.1:8000/api/abteilungsleiter/abrechnungen'

// Detail-Typ für einen einzelnen Stundeneintrag
interface TimesheetEntry {
  datum: string
  beginn: string
  ende: string
  dauer: number
  kurs: string
}

// Haupt-Typ für die Abrechnung
interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  zeitraum: string
  stunden: number
  datumEingereicht: string
  // NEU: Das Array der Details
  details: TimesheetEntry[]
}

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)

// Speichert, welche IDs gerade aufgeklappt sind
const expandedIds = ref<number[]>([])

async function fetchReleaseSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedIds.value = [] // Reset beim Laden

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
  } catch (error: any) {
    errorMessage.value =
        error?.response?.data?.message ||
        'Die Abrechnungen zur Freigabe konnten nicht geladen werden.'
  } finally {
    isLoading.value = false
  }
}

async function approveSubmission(id: number) {
  if (!confirm("Möchtest du diese Abrechnung wirklich genehmigen?")) return
  isProcessingId.value = id

  try {
    await axios.post(`${API_URL}/${id}/approve`)
    submissions.value = submissions.value.filter(item => item.AbrechnungID !== id)
  } catch (error: any) {
    alert("Fehler beim Genehmigen: " + (error.response?.data?.message || "Unbekannter Fehler"))
  } finally {
    isProcessingId.value = null
  }
}

// Funktion zum Auf-/Zuklappen
function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) {
    expandedIds.value = expandedIds.value.filter(x => x !== id)
  } else {
    expandedIds.value.push(id)
  }
}

onMounted(() => {
  fetchReleaseSubmissions()
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
        <h3 class="ma-0">Abrechnungen freigeben</h3>
        <v-btn
            size="small"
            variant="text"
            color="primary"
            :loading="isLoading"
            @click="fetchReleaseSubmissions"
        >
          Aktualisieren
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2"></v-progress-circular>
          Daten werden geladen ...
        </div>

        <v-alert
            v-else-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
        >
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="submissions.length > 0" class="list">
          <div
              v-for="item in submissions"
              :key="item.AbrechnungID"
              class="submission-wrapper"
          >
            <div
                class="submission-row"
                @click="toggleDetails(item.AbrechnungID)"
            >
              <div class="submission-main">
                <div class="line">
                  <span class="label">Mitarbeiter:</span>
                  <span class="value font-weight-bold">{{ item.mitarbeiterName }}</span>
                </div>
                <div class="line">
                  <span class="label">Zeitraum:</span>
                  <span class="value">{{ item.zeitraum }}</span>
                </div>
                <div class="line">
                  <span class="label">Eingereicht am:</span>
                  <span class="value">{{ item.datumEingereicht }}</span>
                </div>
                <div class="line">
                  <span class="label">Gesamt:</span>
                  <span class="value font-weight-bold text-primary">
                    {{ item.stunden.toLocaleString('de-DE') }} Std.
                  </span>
                </div>
              </div>

              <div class="submission-actions">
                <v-icon
                    :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                    class="mr-4 text-medium-emphasis"
                ></v-icon>

                <v-btn
                    size="small"
                    color="success"
                    variant="flat"
                    :loading="isProcessingId === item.AbrechnungID"
                    :disabled="isProcessingId !== null"
                    @click.stop="approveSubmission(item.AbrechnungID)"
                    prepend-icon="mdi-check"
                >
                  Freigeben
                </v-btn>
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedIds.includes(item.AbrechnungID)" class="details-container">
                <v-table density="compact" class="bg-transparent">
                  <thead>
                  <tr>
                    <th class="text-left">Datum</th>
                    <th class="text-left">Zeit</th>
                    <th class="text-left">Kurs/Info</th>
                    <th class="text-right">Dauer</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(detail, i) in item.details" :key="i">
                    <td>{{ detail.datum }}</td>
                    <td>{{ detail.beginn }} - {{ detail.ende }}</td>
                    <td>{{ detail.kurs }}</td>
                    <td class="text-right">{{ detail.dauer }} Std.</td>
                  </tr>
                  </tbody>
                </v-table>
              </div>
            </v-expand-transition>
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-check-all" size="large" color="success" class="mb-2"></v-icon>
          Aktuell keine offenen Freigaben vorhanden.
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 800px;
  margin: 0 auto;
}

.placeholder {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: rgba(0, 0, 0, 0.6);
  font-size: 1rem;
  border-radius: 8px;
  background: rgba(0,0,0,0.02);
  margin-top: 8px;
  padding: 16px;
  text-align: center;
}

.list {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Wrapper um Row + Details */
.submission-wrapper {
  background: white;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  overflow: hidden; /* Für saubere Ecken */
  transition: box-shadow 0.2s;
}

.submission-wrapper:hover {
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.submission-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  cursor: pointer; /* Zeigt Klickbarkeit an */
}

.submission-row:hover {
  background-color: #f9f9f9;
}

.submission-main {
  flex: 1;
}

.line {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
}
.line:last-child { margin-bottom: 0; }

.label {
  min-width: 120px;
  font-weight: 500;
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.9rem;
}

.value {
  font-weight: 400;
  color: rgba(0, 0, 0, 0.87);
}

.submission-actions {
  display: flex;
  align-items: center;
  margin-left: 24px;
}

/* Der Detailbereich */
.details-container {
  background-color: #fafafa;
  border-top: 1px solid rgba(0,0,0,0.06);
  padding: 0 16px 16px 16px;
}
</style>