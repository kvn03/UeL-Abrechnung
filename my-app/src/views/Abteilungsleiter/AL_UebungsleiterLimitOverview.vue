<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

interface LimitData {
  uebungsleiter_id: number
  uebungsleiter_name: string
  abteilung: string
  limit: number
  usedAmount: number
  remaining: number
}

const API_URL = import.meta.env.VITE_API_URL + '/api'
const limits = ref<LimitData[]>([])
const loading = ref(false)
const error = ref('')

function goBack() {
  router.push({ name: 'Dashboard' })
}

onMounted(async () => {
  await fetchLimits()
})

async function fetchLimits() {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('auth_token')
    if (!token) {
      error.value = 'Authentifizierung erforderlich'
      return
    }

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    const response = await axios.get(`${API_URL}/limits/trainer-overview?view=abteilungsleiter`)
    limits.value = response.data.limits || []
  } catch (err: any) {
    console.error('Fehler beim Laden der Limits:', err)
    error.value = err.response?.data?.message || 'Fehler beim Laden der Limits'
  } finally {
    loading.value = false
  }
}

function getProgressPercentage(used: number, limit: number): number {
  if (limit === 0) return 0
  return Math.min((used / limit) * 100, 100)
}
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

    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h2>Übungsleiter Limit-Übersicht</h2>
        <div class="text-subtitle-1 text-medium-emphasis">
          Auslastung der Übungsleiter in deiner Abteilung.
        </div>
      </div>
    </div>

    <v-card class="pa-4" :loading="loading">
      <template v-if="error">
        <v-alert type="error" variant="tonal" class="mb-4">
          {{ error }}
        </v-alert>
      </template>

      <template v-if="loading">
        <div class="d-flex justify-center my-6">
          <v-progress-circular indeterminate color="primary" />
        </div>
      </template>

      <template v-else>
        <div v-if="!limits.length" class="text-medium-emphasis pa-6">
          Keine Daten vorhanden.
        </div>

        <div v-else class="table-wrapper">
          <table class="limit-table">
            <thead>
            <tr>
              <th>Übungsleiter</th>
              <th>Abteilung</th>
              <th>Verwendet</th>
              <th>Verbleibend</th>
              <th>Auslastung</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in limits" :key="item.uebungsleiter_id">
              <td>{{ item.uebungsleiter_name }}</td>
              <td>{{ item.abteilung }}</td>
              <td>{{ item.usedAmount.toFixed(2) }} €</td>
              <td :class="{ 'negative': item.remaining < 0 }">
                {{ item.remaining.toFixed(2) }} €
              </td>
              <td>
                <div class="progress-bar">
                  <div
                      class="progress"
                      :style="{ width: getProgressPercentage(item.usedAmount, item.limit) + '%' }"
                  />
                </div>
                <span class="progress-text">{{ getProgressPercentage(item.usedAmount, item.limit).toFixed(0) }}%</span>
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </template>
    </v-card>
  </div>
</template>

<style scoped>
.page {
  padding: 20px;
}

h2 {
  margin: 0;
  font-weight: 600;
  font-size: 1.5rem;
}

.text-subtitle-1 {
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.text-medium-emphasis {
  color: rgba(0, 0, 0, 0.6);
}

.table-wrapper {
  overflow-x: auto;
}

.limit-table {
  width: 100%;
  border-collapse: collapse;
}

.limit-table th,
.limit-table td {
  border-bottom: 1px solid #eee;
  padding: 0.75rem;
  text-align: left;
  vertical-align: middle;
}

.limit-table thead th {
  background-color: #f5f5f5;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}

.limit-table tbody tr:hover {
  background-color: #fafafa;
}

.limit-table tr:nth-child(even) {
  background-color: #fafafa;
}

.negative {
  color: #dc3545;
  font-weight: 600;
}

.progress-bar {
  width: 100%;
  height: 20px;
  background-color: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 5px;
  position: relative;
}

.progress {
  height: 100%;
  background-color: #28a745;
  transition: width 0.3s ease;
}

.progress-bar:has(.progress[style*="width: 100%"]) .progress,
.progress-bar:has(.progress[style*="width: 99%"]) .progress,
.progress-bar:has(.progress[style*="width: 98%"]) .progress {
  background-color: #dc3545;
}

.progress-text {
  font-size: 0.75rem;
  color: #666;
}

.d-flex {
  display: flex;
}

.justify-space-between {
  justify-content: space-between;
}

.justify-start {
  justify-content: flex-start;
}

.align-center {
  align-items: center;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

.mb-6 {
  margin-bottom: 2rem;
}

.pa-4 {
  padding: 1.5rem;
}

.pa-6 {
  padding: 2rem;
}

.my-6 {
  margin-top: 2rem;
  margin-bottom: 2rem;
}
</style>
