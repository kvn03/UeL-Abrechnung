<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()

// --- Typen & Interfaces ---
interface UserWithRate {
  id: number
  name: string
  vorname: string
  email: string
  abteilungen: string  // Name der Abteilung
  abteilung_id: number // <--- Wichtig für den Filter und das Speichern
  aktuellerSatz: number | null
  gueltigSeit: string | null
}

// --- State ---
const isLoading = ref(false)
const isSaving = ref(false)
const users = ref<UserWithRate[]>([])

// Filter State
const search = ref('')
const filterDept = ref<number | null>(null) // ID der gewählten Abteilung
const filterUser = ref<number | null>(null) // ID des gewählten Users

const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Dialog State
const showDialog = ref(false)
const editForm = ref<VForm | null>(null)
const selectedUser = ref<UserWithRate | null>(null)
const newRate = ref<number | string>('')
const validFrom = ref<string>('')

// --- API Endpoints ---
const API_ALL_USERS = 'http://127.0.0.1:8000/api/geschaeftsstelle/mitarbeiter'
const API_UPDATE_RATE = 'http://127.0.0.1:8000/api/geschaeftsstelle/stundensatz'

// --- Initialisierung ---
onMounted(async () => {
  await loadAllUsers()
})

// --- Data Loading ---
async function loadAllUsers() {
  isLoading.value = true
  errorMessage.value = null
  try {
    const response = await axios.get(API_ALL_USERS)
    users.value = response.data
  } catch (e) {
    console.error(e)
    errorMessage.value = 'Konnte Mitarbeiterliste nicht laden.'
  } finally {
    isLoading.value = false
  }
}

// --- Computed: Filter-Optionen (Dynamisch aus den Daten) ---

// Erstellt eine Liste aller vorhandenen Abteilungen (ohne Duplikate)
const departmentOptions = computed(() => {
  const map = new Map<number, string>()
  users.value.forEach(u => {
    if (u.abteilung_id && u.abteilungen) {
      map.set(u.abteilung_id, u.abteilungen)
    }
  })
  // Map in Array umwandeln für v-select
  return Array.from(map, ([id, name]) => ({ title: name, value: id }))
      .sort((a, b) => a.title.localeCompare(b.title))
})

// Erstellt eine Liste aller User-Namen (ohne Duplikate)
const userOptions = computed(() => {
  const map = new Map<number, string>()
  users.value.forEach(u => {
    map.set(u.id, `${u.vorname} ${u.name}`)
  })
  return Array.from(map, ([id, name]) => ({ title: name, value: id }))
      .sort((a, b) => a.title.localeCompare(b.title))
})

// --- Computed: Gefilterte Liste für die Tabelle ---
const filteredUsers = computed(() => {
  return users.value.filter(item => {
    // 1. Abteilungs-Filter
    if (filterDept.value !== null && item.abteilung_id !== filterDept.value) {
      return false
    }
    // 2. User-Filter
    if (filterUser.value !== null && item.id !== filterUser.value) {
      return false
    }
    return true
  })
})

// --- Edit Dialog ---
function openEditDialog(user: UserWithRate) {
  selectedUser.value = user
  newRate.value = user.aktuellerSatz || ''

  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  validFrom.value = tomorrow.toISOString().split('T')[0]

  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  selectedUser.value = null
  newRate.value = ''
  errorMessage.value = null
}

// --- Speichern ---
async function saveRate() {
  const { valid } = await editForm.value?.validate() || { valid: false }
  if (!valid || !selectedUser.value) return

  isSaving.value = true
  errorMessage.value = null

  const payload = {
    user_id: selectedUser.value.id,
    abteilung_id: selectedUser.value.abteilung_id,
    satz: parseFloat(newRate.value.toString()),
    gueltig_ab: validFrom.value
  }

  try {
    await axios.post(API_UPDATE_RATE, payload)

    successMessage.value = `Stundensatz für ${selectedUser.value.vorname} ${selectedUser.value.name} aktualisiert.`
    setTimeout(() => successMessage.value = null, 3000)

    closeDialog()
    await loadAllUsers()

  } catch (error: any) {
    console.error(error)
    errorMessage.value = error.response?.data?.message || 'Fehler beim Speichern.'
  } finally {
    isSaving.value = false
  }
}

// --- Helper ---
function formatCurrency(value: number | null) {
  if (value === null || value === undefined) return '-'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value)
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return '-'
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
        <h3 class="ma-0">Alle Stundensätze verwalten (Geschäftsstelle)</h3>
        <div class="text-caption text-medium-emphasis">Übersicht aller Übungsleiter nach Abteilung</div>
      </div>

      <v-row dense class="mb-2 align-center">
        <v-col cols="12" md="4">
          <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Suche (Text)"
              variant="outlined"
              density="compact"
              hide-details
              clearable
          ></v-text-field>
        </v-col>

        <v-col cols="12" md="4">
          <v-autocomplete
              v-model="filterDept"
              :items="departmentOptions"
              label="Nach Abteilung filtern"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              placeholder="Alle Abteilungen"
          ></v-autocomplete>
        </v-col>

        <v-col cols="12" md="4">
          <v-autocomplete
              v-model="filterUser"
              :items="userOptions"
              label="Nach Mitarbeiter filtern"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              placeholder="Alle Mitarbeiter"
          ></v-autocomplete>
        </v-col>
      </v-row>

      <v-card-text class="pa-0 mt-4">
        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable>{{ errorMessage }}</v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable>{{ successMessage }}</v-alert>

        <v-data-table
            :headers="[
            { title: 'Name', key: 'name' },
            { title: 'Abteilung', key: 'abteilungen' },
            { title: 'Aktueller Satz', key: 'aktuellerSatz', align: 'end' },
            { title: 'Gültig seit', key: 'gueltigSeit', align: 'end' },
            { title: 'Aktion', key: 'actions', align: 'end', sortable: false }
          ]"
            :items="filteredUsers"
            :search="search"
            :loading="isLoading"
            hover
            density="comfortable"
        >
          <template v-slot:item.name="{ item }">
            <div class="font-weight-medium">{{ item.name }}, {{ item.vorname }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
          </template>

          <template v-slot:item.abteilungen="{ item }">
            <v-chip size="small" color="blue-grey-lighten-4" class="font-weight-medium">
              {{ item.abteilungen }}
            </v-chip>
          </template>

          <template v-slot:item.aktuellerSatz="{ item }">
            <v-chip :color="item.aktuellerSatz ? 'green' : 'grey'" variant="flat" size="small" class="font-weight-bold">
              {{ formatCurrency(item.aktuellerSatz) }}
            </v-chip>
          </template>

          <template v-slot:item.gueltigSeit="{ item }">
            {{ formatDate(item.gueltigSeit) }}
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
                color="primary"
                variant="text"
                icon="mdi-pencil"
                @click="openEditDialog(item)"
                title="Satz ändern"
            ></v-btn>
          </template>

          <template v-slot:no-data>
            <div class="pa-4 text-center text-medium-emphasis">Keine Einträge gefunden.</div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="500px">
      <v-card v-if="selectedUser">
        <v-card-title class="bg-primary text-white">
          Stundensatz ändern
        </v-card-title>
        <v-card-subtitle class="pt-3">
          Mitarbeiter: <b>{{ selectedUser.vorname }} {{ selectedUser.name }}</b> <br>
          Abteilung: <b>{{ selectedUser.abteilungen }}</b>
        </v-card-subtitle>

        <v-card-text class="pt-4">
          <v-form ref="editForm" @submit.prevent="saveRate">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4 text-caption">
              Der aktuelle Satz ({{ formatCurrency(selectedUser.aktuellerSatz) }}) wird zum Tag vor dem "Gültig ab"-Datum beendet.
            </v-alert>

            <v-text-field
                v-model="newRate"
                label="Neuer Stundensatz (€)"
                type="number"
                step="0.01"
                variant="outlined"
                suffix="€"
                :rules="[v => !!v || 'Pflichtfeld', v => v > 0 || 'Muss > 0 sein']"
            ></v-text-field>

            <v-text-field
                v-model="validFrom"
                label="Gültig ab"
                type="date"
                variant="outlined"
                :rules="[v => !!v || 'Pflichtfeld']"
            ></v-text-field>
          </v-form>
        </v-card-text>

        <v-card-actions class="justify-end pa-4 pt-0">
          <v-btn variant="text" @click="closeDialog">Abbrechen</v-btn>
          <v-btn
              color="primary"
              variant="elevated"
              @click="saveRate"
              :loading="isSaving"
          >
            Speichern
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 1200px; margin: 0 auto; }
</style>