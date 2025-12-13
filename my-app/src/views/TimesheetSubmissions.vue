<!-- my-app/src/views/TimesheetSubmissions.vue -->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

// [BACKEND] Hier einfach die finale URL eintragen
const API_URL = 'http://127.0.0.1:8000/api/abrechnung/summary'

// State für die Abrechnungsübersicht
const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)

const status = ref<string | null>(null)
const totalHours = ref<number | null>(null)
const totalAmount = ref<number | null>(null)

// Typ für die erwartete API-Antwort (nur zur Doku/Zeitersparnis)
interface TimesheetSummaryResponse {
  status: string
  totalHours: number
  totalAmount: number
}

// Funktion, die die Daten vom Backend holt
async function fetchTimesheetSummary() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await axios.get<TimesheetSummaryResponse>(API_URL)

    // Response-Daten in den State schreiben
    status.value = response.data.status
    totalHours.value = response.data.totalHours
    totalAmount.value = response.data.totalAmount
  } catch (error: any) {
    // [BACKEND] hier kann der Backend-Dev bei Bedarf die Fehlerstruktur anpassen
    errorMessage.value =
        error?.response?.data?.message ||
        'Die Abrechnungsübersicht konnte nicht geladen werden.'
  } finally {
    isLoading.value = false
  }
}

// Beim Laden der Seite automatisch Daten ziehen
onMounted(() => {
  fetchTimesheetSummary()
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
        <h3 class="ma-0">Abrechnungsübersicht</h3>

        <v-btn
            size="small"
            variant="text"
            color="primary"
            :loading="isLoading"
            @click="fetchTimesheetSummary"
        >
          Aktualisieren
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <!-- Ladezustand -->
        <div v-if="isLoading" class="placeholder">
          Daten werden geladen ...
        </div>

        <!-- Fehlermeldung -->
        <v-alert
            v-else-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
        >
          {{ errorMessage }}
        </v-alert>

        <!-- Inhalt, wenn Daten da sind -->
        <div v-else-if="status !== null" class="summary">
          <div class="summary-row">
            <span class="label">Status der Abrechnung:</span>
            <span class="value">
              {{ status }}
            </span>
          </div>

          <div class="summary-row">
            <span class="label">Summe Stunden:</span>
            <span class="value">
              {{ totalHours?.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
              Std.
            </span>
          </div>

          <div class="summary-row">
            <span class="label">Summe Betrag:</span>
            <span class="value">
              {{ totalAmount?.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }) }}
            </span>
          </div>
        </div>

        <!-- Fallback, falls noch keine Daten vorhanden sind -->
        <div v-else class="placeholder">
          Hier siehst du später deine eingereichten Abrechnungen.
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 640px;
  margin: 0 auto;
}

.placeholder {
  min-height: 220px;
  display: flex;
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

.summary {
  margin-top: 8px;
  padding: 16px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.02);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.summary-row:last-child {
  margin-bottom: 0;
}

.label {
  font-weight: 500;
  color: rgba(0, 0, 0, 0.7);
}

.value {
  font-weight: 600;
}
</style>
