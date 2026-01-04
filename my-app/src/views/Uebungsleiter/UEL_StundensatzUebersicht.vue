<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

// --- Interface ---
interface RateEntry {
  satz: number
  gueltigVon: string
  gueltigBis: string | null
  abteilung: string
  abteilung_id: number
}

// Hilfs-Struktur für die Gruppierung
interface GroupedRates {
  abteilung: string
  currentRate: RateEntry | null
  history: RateEntry[]
}

// --- State ---
const isLoading = ref(false)
const rawRates = ref<RateEntry[]>([])
const errorMessage = ref<string | null>(null)

const API_URL = 'http://127.0.0.1:8000/api/uebungsleiter/meine-saetze'

// --- Computed: Gruppierung nach Abteilung ---
const groupedRates = computed(() => {
  const groups = new Map<number, GroupedRates>()

  rawRates.value.forEach(entry => {
    if (!groups.has(entry.abteilung_id)) {
      groups.set(entry.abteilung_id, {
        abteilung: entry.abteilung,
        currentRate: null,
        history: []
      })
    }

    const group = groups.get(entry.abteilung_id)!

    // Logik: Wenn gueltigBis NULL ist -> Aktuell
    // ODER: Der Eintrag mit dem neuesten gueltigVon (da wir vom Backend sortiert bekommen)
    // Wir nehmen hier an: gueltigBis === null ist der aktive Satz.
    if (entry.gueltigBis === null) {
      group.currentRate = entry
    } else {
      group.history.push(entry)
    }
  })

  // Map zu Array umwandeln
  return Array.from(groups.values())
})

// --- Init ---
onMounted(async () => {
  isLoading.value = true
  try {
    const response = await axios.get(API_URL)
    rawRates.value = response.data
  } catch (e) {
    errorMessage.value = 'Konnte Stundensätze nicht laden.'
  } finally {
    isLoading.value = false
  }
})

// --- Helper ---
function formatCurrency(val: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

function formatDate(dateStr: string) {
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
        <h3 class="ma-0">Meine Stundensätze</h3>
        <div class="text-caption text-medium-emphasis">Übersicht deiner aktuellen und vergangenen Stundensätze pro Abteilung.</div>
      </div>

      <div v-if="isLoading" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate color="primary"></v-progress-circular>
      </div>

      <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">
        {{ errorMessage }}
      </v-alert>

      <div v-else-if="groupedRates.length > 0">
        <v-expansion-panels variant="popout" class="mt-4">

          <v-expansion-panel v-for="(group, index) in groupedRates" :key="index">

            <v-expansion-panel-title>
              <v-row no-gutters align="center">
                <v-col cols="12" sm="6" class="d-flex align-center">
                  <v-icon icon="mdi-domain" start color="grey-darken-1"></v-icon>
                  <span class="text-subtitle-1 font-weight-bold">{{ group.abteilung }}</span>
                </v-col>
                <v-col cols="12" sm="6" class="text-sm-right mt-2 mt-sm-0">
                  <span class="text-caption text-medium-emphasis mr-2">Aktuell:</span>
                  <v-chip v-if="group.currentRate" color="green" variant="flat" class="font-weight-bold">
                    {{ formatCurrency(group.currentRate.satz) }}
                  </v-chip>
                  <v-chip v-else color="grey" size="small">Kein aktiver Satz</v-chip>
                </v-col>
              </v-row>
            </v-expansion-panel-title>

            <v-expansion-panel-text>
              <div class="pt-2">

                <div v-if="group.currentRate" class="mb-4 bg-green-lighten-5 pa-3 rounded border-green">
                  <div class="text-subtitle-2 text-green-darken-3 mb-1">Aktuell gültige Vereinbarung</div>
                  <div class="d-flex justify-space-between align-center">
                    <span>Betrag: <b>{{ formatCurrency(group.currentRate.satz) }}</b> / Std.</span>
                    <span class="text-caption">Gültig seit: {{ formatDate(group.currentRate.gueltigVon) }}</span>
                  </div>
                </div>

                <v-divider class="mb-3"></v-divider>

                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-2">Historie (Archiv)</div>

                <v-table density="compact" v-if="group.history.length > 0">
                  <thead>
                  <tr>
                    <th class="text-left">Zeitraum</th>
                    <th class="text-right">Satz</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(hist, i) in group.history" :key="i">
                    <td class="text-medium-emphasis">
                      {{ formatDate(hist.gueltigVon) }} bis {{ hist.gueltigBis ? formatDate(hist.gueltigBis) : 'heute' }}
                    </td>
                    <td class="text-right text-decoration-line-through text-medium-emphasis">
                      {{ formatCurrency(hist.satz) }}
                    </td>
                  </tr>
                  </tbody>
                </v-table>

                <div v-else class="text-caption font-italic text-grey py-2">
                  Keine veralteten Sätze vorhanden.
                </div>

              </div>
            </v-expansion-panel-text>
          </v-expansion-panel>

        </v-expansion-panels>
      </div>

      <div v-else class="placeholder">
        <v-icon icon="mdi-cash-remove" size="large" class="mb-2 opacity-50"></v-icon>
        Es sind noch keine Stundensätze für dich hinterlegt.
      </div>

    </v-card>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 800px; margin: 0 auto; }
.placeholder { min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(0, 0, 0, 0.6); margin-top: 16px; background: rgba(0,0,0,0.02); border-radius: 8px; }
.border-green { border: 1px solid #A5D6A7; }
</style>