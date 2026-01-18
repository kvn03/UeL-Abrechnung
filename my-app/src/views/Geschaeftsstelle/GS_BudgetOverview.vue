<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const API_URL = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/pauschale'

interface BudgetRow {
  user_id: number
  name: string
  used: number
  limit: number
  percent: number
}

const isLoading = ref(false)
const items = ref<BudgetRow[]>([])
const search = ref('')

// Farbe basierend auf Prozent
function getColor(percent: number) {
  if (percent >= 100) return 'red-darken-1'
  if (percent >= 90) return 'orange-darken-1'
  if (percent >= 75) return 'amber-darken-1'
  return 'success'
}

function formatCurrency(val: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

async function fetchBudgets() {
  isLoading.value = true
  try {
    const response = await axios.get<BudgetRow[]>(API_URL)
    items.value = response.data
  } catch (e) {
    console.error(e)
    alert("Fehler beim Laden der Budgets")
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchBudgets()
})
</script>

<template>
  <div class="page pa-4" style="max-width: 1000px; margin: 0 auto;">
    <div class="d-flex justify-start mb-4">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="router.push({ name: 'Dashboard' })">
        Zurück
      </v-btn>
    </div>

    <v-card elevation="6">
      <v-card-title class="d-flex justify-space-between align-center py-3 px-4">
        <span>Ehrenamtspauschale Übersicht ({{ new Date().getFullYear() }})</span>
        <v-btn icon="mdi-refresh" variant="text" :loading="isLoading" @click="fetchBudgets"></v-btn>
      </v-card-title>

      <v-card-text>
        <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Suchen..."
            variant="outlined"
            density="compact"
            hide-details
            class="mb-4"
        ></v-text-field>

        <v-data-table
            :headers="[
            { title: 'Name', key: 'name' },
            { title: 'Verbraucht', key: 'used', align: 'end' },
            { title: 'Status', key: 'percent', align: 'start' },
          ]"
            :items="items"
            :search="search"
            :loading="isLoading"
            density="comfortable"
        >
          <template v-slot:item.used="{ item }">
            <div class="font-weight-bold">
              {{ formatCurrency(item.used) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              von {{ formatCurrency(item.limit) }}
            </div>
          </template>

          <template v-slot:item.percent="{ item }">
            <div style="min-width: 150px">
              <div class="d-flex justify-space-between text-caption mb-1">
                <strong>{{ item.percent }}%</strong>
                <span v-if="item.percent >= 100" class="text-red font-weight-bold">Limit erreicht!</span>
              </div>
              <v-progress-linear
                  :model-value="item.percent"
                  :color="getColor(item.percent)"
                  height="10"
                  rounded
                  striped
              ></v-progress-linear>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>