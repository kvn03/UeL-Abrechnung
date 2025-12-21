<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_URL = 'http://127.0.0.1:8000/api/geschaeftsstelle/abrechnungen-historie'

interface HistoryItem {
  AbrechnungID: number
  mitarbeiterName: string
  abteilung: string
  zeitraum: string
  stunden: number
  status: string
}

const currentYear = new Date().getFullYear()

// Aktuelles Quartal dynamisch berechnen: 0=Jan,1=Feb,...
const monthIndex = new Date().getMonth() // 0-11
const currentQuarter = monthIndex <= 2 ? 'Q1' : monthIndex <= 5 ? 'Q2' : monthIndex <= 8 ? 'Q3' : 'Q4'

const selectedYear = ref<number>(currentYear)
const selectedQuarter = ref<string>(currentQuarter)

const years = computed(() => {
  // einfache Auswahl: aktuelles Jahr und die letzten 4 Jahre
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
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Abrechnungshistorie.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchHistory()
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
      <v-card-title class="pa-0 mb-4 d-flex flex-column flex-sm-row align-sm-center justify-space-between">
        <div>
          <h3 class="ma-0">Abrechnungshistorie (Geschäftsstelle)</h3>
          <div class="text-body-2 text-medium-emphasis mt-1">
            Quartalsübersicht aller Abrechnungen im gewählten Jahr.
          </div>
        </div>

        <div class="filters d-flex flex-wrap mt-3 mt-sm-0">
          <v-select
            v-model="selectedYear"
            :items="years"
            label="Jahr"
            density="comfortable"
            variant="outlined"
            class="mr-sm-2 mb-2 mb-sm-0"
            style="max-width: 120px"
            @update:model-value="fetchHistory"
          />

          <v-select
            v-model="selectedQuarter"
            :items="quarters"
            item-title="label"
            item-value="value"
            label="Quartal"
            density="comfortable"
            variant="outlined"
            style="max-width: 180px"
            @update:model-value="fetchHistory"
          />
        </div>
      </v-card-title>

      <v-card-text class="pa-0 mt-2">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2" />
          Lade Abrechnungshistorie ...
        </div>

        <v-alert
          v-else-if="errorMessage"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="items.length > 0" class="list">
          <v-table density="comfortable">
            <thead>
              <tr>
                <th class="text-left">Abrechnung</th>
                <th class="text-left">Mitarbeiter</th>
                <th class="text-left">Abteilung</th>
                <th class="text-left">Zeitraum</th>
                <th class="text-right">Stunden</th>
                <th class="text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.AbrechnungID">
                <td>#{{ item.AbrechnungID }}</td>
                <td>{{ item.mitarbeiterName }}</td>
                <td>{{ item.abteilung }}</td>
                <td>{{ item.zeitraum }}</td>
                <td class="text-right">{{ item.stunden.toLocaleString('de-DE') }}</td>
                <td>{{ item.status }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-history" size="large" color="blue-grey" class="mb-2" />
          Keine Abrechnungen im ausgewählten Zeitraum gefunden.
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 1000px;
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
  background: rgba(0, 0, 0, 0.02);
  border-radius: 8px;
  margin-top: 8px;
  padding: 16px;
}

.list {
  margin-top: 8px;
}

.filters {
  gap: 8px;
}
</style>
