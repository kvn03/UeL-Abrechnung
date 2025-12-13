<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

// URL zum neuen Geschäftsstellen-Endpoint
const API_URL = 'http://127.0.0.1:8000/api/geschaeftsstelle/abrechnungen'

interface TimesheetEntry {
  datum: string
  dauer: number
  kurs: string
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  abteilung: string
  zeitraum: string
  stunden: number
  datumGenehmigtAL: string
  genehmigtDurch: string; // <--- NEU
  details: TimesheetEntry[]
}

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)
const expandedIds = ref<number[]>([])

async function fetchSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedIds.value = []

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Abrechnungen.'
  } finally {
    isLoading.value = false
  }
}

async function finalizeSubmission(id: number) {
  if (!confirm("Möchtest du diese Abrechnung final freigeben (zur Auszahlung)?")) return

  isProcessingId.value = id
  try {
    await axios.post(`${API_URL}/${id}/finalize`)
    // Aus Liste entfernen
    submissions.value = submissions.value.filter(s => s.AbrechnungID !== id)
  } catch (error: any) {
    alert("Fehler: " + (error.response?.data?.message || "Konnte nicht freigegeben werden."))
  } finally {
    isProcessingId.value = null
  }
}

function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) {
    expandedIds.value = expandedIds.value.filter(x => x !== id)
  } else {
    expandedIds.value.push(id)
  }
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
        <h3 class="ma-0">Finale Freigabe (Geschäftsstelle)</h3>
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
          Lade genehmigte Abrechnungen ...
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
            <div class="submission-row" @click="toggleDetails(item.AbrechnungID)">
              <div class="submission-main">
                <div class="d-flex justify-space-between mb-1">
                  <span class="text-h6 font-weight-bold text-primary">{{ item.mitarbeiterName }}</span>
                  <v-chip size="small" color="blue-grey" variant="flat">{{ item.abteilung }}</v-chip>
                </div>

                <div class="line">
                  <span class="label">Zeitraum:</span>
                  <span class="value">{{ item.zeitraum }}</span>
                </div>
                <div class="line">
                  <span class="label">Genehmigt am:</span>
                  <span class="value">{{ item.datumGenehmigtAL }}</span>
                </div>
                <div class="line">
                  <span class="label">Genehmigt am:</span>
                  <span class="value">{{ item.datumGenehmigtAL }}</span>
                </div>

                <div class="line">
                  <span class="label">Durch:</span>
                  <span class="value">{{ item.genehmigtDurch }}</span>
                </div>
                <div class="line mt-1">
                  <span class="label">Summe:</span>
                  <span class="value font-weight-bold">
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
                    color="info"
                    variant="flat"
                    prepend-icon="mdi-cash-check"
                    :loading="isProcessingId === item.AbrechnungID"
                    :disabled="isProcessingId !== null"
                    @click.stop="finalizeSubmission(item.AbrechnungID)"
                >
                  Abschließen
                </v-btn>
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedIds.includes(item.AbrechnungID)" class="details-container">
                <v-table density="compact" class="bg-transparent">
                  <thead>
                  <tr>
                    <th class="text-left">Datum</th>
                    <th class="text-left">Info</th>
                    <th class="text-right">Dauer</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(d, i) in item.details" :key="i">
                    <td>{{ d.datum }}</td>
                    <td>{{ d.kurs || '-' }}</td>
                    <td class="text-right">{{ d.dauer }}</td>
                  </tr>
                  </tbody>
                </v-table>
              </div>
            </v-expand-transition>
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-file-check-outline" size="large" color="green" class="mb-2"></v-icon>
          Keine Abrechnungen zur finalen Freigabe vorhanden.
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
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: rgba(0, 0, 0, 0.6);
  font-size: 1rem;
  background: rgba(0,0,0,0.02);
  border-radius: 8px;
  margin-top: 8px;
  padding: 16px;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 8px;
}

.submission-wrapper {
  background: white;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.submission-wrapper:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }

.submission-row {
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}
.submission-row:hover { background-color: #f9f9f9; }

.submission-main { flex: 1; }

.line { display: flex; gap: 8px; margin-bottom: 4px; font-size: 0.9rem; }
.label { min-width: 100px; color: rgba(0,0,0,0.6); font-weight: 500; }
.value { color: rgba(0,0,0,0.87); }

.submission-actions { display: flex; align-items: center; margin-left: 16px; }

.details-container {
  background-color: #fafafa;
  border-top: 1px solid rgba(0,0,0,0.06);
  padding: 0 16px 16px 16px;
}
</style>