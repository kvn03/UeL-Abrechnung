<!-- language: vue -->
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

type Department = {
  id: number
  name: string
}

const router = useRouter()
const form = ref<VForm | null>(null)
const isSubmitting = ref(false)

// Datum: Tag / Monat / Jahr
const day = ref<string>('')
const month = ref<string>('')
const year = ref<string>('')

// Fehlertext für das komplette Datum
const dateError = ref<string>('')

// Dauer nur in Minuten
const durationMinutes = ref<string>('')

// Abteilungen und Auswahl
const departments = ref<Department[]>([])
const selectedDepartment = ref<number | null>(null)

// Kurse/Gruppe: Platzhalter und Auswahl
const courseOptions = ref<string[]>([
  'Kurs 1',
  'Kurs 2',
  'Kurs 3',
  'Kurs 4',
  'Kurs 5',
])
const selectedCourse = ref<string | null>(null)

// Abteilungen vom Backend laden
async function fetchDepartments() {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/abteilungen')
    departments.value = response.data
  } catch (error) {
    console.error('Konnte Abteilungen nicht laden. Ist das Backend gestartet?', error)
  }
}

onMounted(() => {
  fetchDepartments()
})

// Validierungsregeln
const dayRules = [
  (v: string) => !!v || 'Tag ist erforderlich',
  (v: string) => {
    const n = Number(v)
    return (!isNaN(n) && n >= 1 && n <= 31) || 'Tag muss zwischen 1 und 31 liegen'
  },
]

const monthRules = [
  (v: string) => !!v || 'Monat ist erforderlich',
  (v: string) => {
    const n = Number(v)
    return (!isNaN(n) && n >= 1 && n <= 12) || 'Monat muss zwischen 1 und 12 liegen'
  },
]

const yearRules = [
  (v: string) => !!v || 'Jahr ist erforderlich',
  (v: string) => {
    const normalized = normalizeYear(v)
    const n = Number(normalized)
    return (!isNaN(n) && n >= 2000 && n <= 2100) || 'Jahr muss zwischen 2000 und 2100 liegen'
  },
]

const durationMinuteRules = [
  (v: string) => v !== '' || 'Minuten sind erforderlich',
  (v: string) => {
    const n = Number(v)
    return (!isNaN(n) && n > 0) || 'Minuten müssen größer 0 sein'
  },
]

// Pflicht: Abteilung wählen
const departmentRules = [
  (v: number | null) => !!v || 'Bitte eine Abteilung wählen',
]

// Pflicht: Kurs/Gruppe wählen
const courseRules = [
  (v: string | null) => !!v || 'Bitte einen Kurs/Gruppe wählen',
]

// Hilfsfunktionen
function normalizeYear(raw: string): string {
  const trimmed = raw.trim()
  if (!trimmed) return ''
  if (/^\d{2}$/.test(trimmed)) {
    return `20${trimmed}`
  }
  return trimmed
}

function onYearBlur() {
  year.value = normalizeYear(year.value)
  dateError.value = ''
}

// nimmt die drei Felder, baut ein Datum und prüft, ob es existiert
function buildIsoDate(): string | null {
  const yNum = Number(normalizeYear(year.value))
  const mNum = Number(month.value)
  const dNum = Number(day.value)
  if ([yNum, mNum, dNum].some(v => isNaN(v))) return null

  const date = new Date(yNum, mNum - 1, dNum)
  const isValid =
      date.getFullYear() === yNum &&
      date.getMonth() === mNum - 1 &&
      date.getDate() === dNum

  if (!isValid) return null

  const mm = String(mNum).padStart(2, '0')
  const dd = String(dNum).padStart(2, '0')
  return `${yNum}-${mm}-${dd}`
}

function calcDecimalHoursFromMinutes(): number | null {
  const min = Number(durationMinutes.value)
  if (isNaN(min)) return null
  return min / 60
}

async function submitTimesheet() {
  dateError.value = ''

  const { valid } = (await form.value?.validate()) || { valid: false }
  if (!valid) return

  year.value = normalizeYear(year.value)

  const isoDate = buildIsoDate()
  if (!isoDate) {
    dateError.value = 'Das eingegebene Datum ist ungültig.'
    return
  }

  const decimalHours = calcDecimalHoursFromMinutes()
  if (decimalHours === null || decimalHours <= 0) {
    dateError.value = ''
    alert('Die Dauer muss größer als 0 Minuten sein.')
    return
  }

  if (!selectedDepartment.value || !selectedCourse.value) {
    // sollte durch :rules eigentlich schon abgefangen werden
    return
  }

  isSubmitting.value = true
  try {
    // HIER: aktuell nur Konsolen-Ausgabe, KEIN DB-Speichern
    console.log('Submit payload', {
      date: isoDate,
      hours: decimalHours,
      departmentId: selectedDepartment.value,
      course: selectedCourse.value,
    })

    // später z.B.:
    // await axios.post('http://127.0.0.1:8000/api/timesheets', {
    //   date: isoDate,
    //   hours: decimalHours,
    //   department_id: selectedDepartment.value,
    //   course: selectedCourse.value,
    // })

    form.value?.reset()
    day.value = ''
    month.value = ''
    year.value = ''
    durationMinutes.value = ''
    dateError.value = ''
    selectedDepartment.value = null
    selectedCourse.value = null
  } finally {
    isSubmitting.value = false
  }
}

function goBack() {
  router.push({ name: 'Dashboard' })
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

    <v-card elevation="6" class="pa-4">
      <v-card-title class="pa-0 mb-4">
        <h3 class="ma-0">Stundenerfassung</h3>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form ref="form" @submit.prevent="submitTimesheet">
          <!-- Datum -->
          <div class="mb-3">
            <div class="mb-1 text-subtitle-2">Datum</div>
            <div class="d-flex" style="gap: 8px;">
              <v-text-field
                  v-model="day"
                  label="TT"
                  type="number"
                  min="1"
                  max="31"
                  :rules="dayRules"
                  density="comfortable"
                  style="max-width: 90px;"
                  @input="dateError = ''"
              />
              <v-text-field
                  v-model="month"
                  label="MM"
                  type="number"
                  min="1"
                  max="12"
                  :rules="monthRules"
                  density="comfortable"
                  style="max-width: 90px;"
                  @input="dateError = ''"
              />
              <v-text-field
                  v-model="year"
                  label="JJ oder JJJJ"
                  type="number"
                  :rules="yearRules"
                  density="comfortable"
                  style="max-width: 130px;"
                  @blur="onYearBlur"
                  @input="dateError = ''"
              />
            </div>

            <div
                v-if="dateError"
                class="text-caption"
                style="color:#d32f2f; margin-top:4px;"
            >
              {{ dateError }}
            </div>
          </div>

          <!-- Dauer -->
          <div class="mb-3">
            <div class="mb-1 text-subtitle-2">Dauer (in Minuten)</div>
            <v-text-field
                v-model="durationMinutes"
                label="Minuten"
                type="number"
                min="1"
                :rules="durationMinuteRules"
                density="comfortable"
                style="max-width: 160px;"
            />
          </div>

          <!-- Zuordnung (jetzt direkt über dem Button) -->
          <div class="mb-4">
            <div class="mb-1 text-subtitle-2">Zuordnung</div>
            <div class="d-flex flex-wrap" style="gap: 12px;">
              <v-select
                  v-model="selectedDepartment"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  label="Abteilung wählen"
                  variant="outlined"
                  density="compact"
                  placeholder="Bitte wählen..."
                  :rules="departmentRules"
                  no-data-text="Keine Abteilungen geladen"
                  style="min-width: 220px;"
              />
              <v-select
                  v-model="selectedCourse"
                  :items="courseOptions"
                  label="Kurs/Gruppe"
                  variant="outlined"
                  density="compact"
                  placeholder="Bitte wählen..."
                  :rules="courseRules"
                  style="min-width: 200px;"
              />
            </div>
          </div>

          <div class="d-flex justify-center mt-2">
            <v-btn
                color="primary"
                type="submit"
                :loading="isSubmitting"
                :disabled="isSubmitting"
                style="min-width: 180px;"
            >
              Stundenerfassung abschicken
            </v-btn>
          </div>
        </v-form>
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
</style>
