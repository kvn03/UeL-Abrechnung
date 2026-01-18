<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

// ... (Imports bleiben gleich)

const hasChanges = computed(() => {
  if (!isEditMode.value) return true
  if (!originalData.value) return false
  return JSON.stringify(formData.value) !== JSON.stringify(originalData.value)
})

const router = useRouter()
const originalData = ref<any>(null)

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_BASE = import.meta.env.VITE_API_URL+ '/api'
const API_URL = `${API_BASE}/abteilungsleiter/abrechnungen`

// --- TYPES ---
interface TimesheetEntry {
  EintragID: number
  datum: string
  beginn: string
  ende: string
  dauer: number
  kurs: string
  betrag: number | null
  fk_abrechnungID?: number
  isFeiertag?: boolean
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  quartal: string
  zeitraum: string
  stunden: number
  gesamtBetrag: number
  datumEingereicht: string
  details: TimesheetEntry[]
}

// --- STATE ---
const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)
const expandedIds = ref<number[]>([])

// --- STATE: ABLEHNEN ---
const showRejectDialog = ref(false)
const rejectReason = ref('')
const rejectLoading = ref(false)
const idToReject = ref<number | null>(null)

// --- DIALOG STATE ---
const showDialog = ref(false)
const isEditMode = ref(false)
const dialogLoading = ref(false)
const dialogForm = ref<VForm | null>(null)

const currentSubmissionId = ref<number | null>(null)
const currentEntryId = ref<number | null>(null)

const formData = ref({
  datum: '',
  beginn: '',
  ende: '',
  kurs: ''
})

// --- API: LADEN ---
async function fetchReleaseSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedIds.value = []

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
    // Fallback Berechnung
    submissions.value.forEach(sub => {
      if (sub.gesamtBetrag === undefined || sub.gesamtBetrag === null) {
        sub.gesamtBetrag = sub.details.reduce((sum, entry) => sum + (Number(entry.betrag) || 0), 0)
      }
    })
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden.'
  } finally {
    isLoading.value = false
  }
}

// --- API: FREIGEBEN ---
async function approveSubmission(id: number) {
  if (!confirm("Möchtest du diese Abrechnung wirklich genehmigen?")) return
  isProcessingId.value = id
  try {
    await axios.post(`${API_URL}/${id}/approve`)
    submissions.value = submissions.value.filter(item => item.AbrechnungID !== id)
  } catch (error: any) {
    alert("Fehler: " + (error.response?.data?.message || "Unbekannter Fehler"))
  } finally {
    isProcessingId.value = null
  }
}

// --- API: ABLEHNEN ---
function openRejectDialog(id: number) {
  idToReject.value = id
  rejectReason.value = ''
  showRejectDialog.value = true
}

async function submitRejection() {
  if (!idToReject.value) return
  if (!rejectReason.value || rejectReason.value.length < 5) {
    alert("Bitte gib eine Begründung an (mind. 5 Zeichen).")
    return
  }
  rejectLoading.value = true
  try {
    await axios.post(`${API_URL}/${idToReject.value}/reject`, {
      grund: rejectReason.value
    })
    submissions.value = submissions.value.filter(item => item.AbrechnungID !== idToReject.value)
    showRejectDialog.value = false
    alert("Abrechnung wurde abgelehnt.")
  } catch (error: any) {
    alert("Fehler beim Ablehnen: " + (error.response?.data?.message || error.message))
  } finally {
    rejectLoading.value = false
  }
}

// --- HELPER & ACTIONS ---
function recalculateTotal(submissionId: number) {
  const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
  if (subIndex === -1) return
  const sub = submissions.value[subIndex]
  const totalHours = sub.details.reduce((sum, entry) => sum + Number(entry.dauer), 0)
  sub.stunden = parseFloat(totalHours.toFixed(2))
  const totalMoney = sub.details.reduce((sum, entry) => sum + (Number(entry.betrag) || 0), 0)
  sub.gesamtBetrag = parseFloat(totalMoney.toFixed(2))
}

function formatCurrency(val: number | null | undefined) {
  if (val === null || val === undefined) return '-'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

function formatNumber(val: number) {
  return val.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 2 })
}

// --- WICHTIG: DATUM FORMATIEREN ---
function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

// --- WICHTIG: ZEIT FORMATIEREN (Sekunden abschneiden) ---
function formatTime(timeStr: string) {
  if (!timeStr) return ''
  // Schneidet "14:30:00" nach "14:30" ab
  return timeStr.substring(0, 5)
}

async function deleteEntry(entry: TimesheetEntry, submissionId: number) {
  if (!confirm(`Eintrag vom ${entry.datum} wirklich löschen?`)) return
  try {
    await axios.delete(`${API_BASE}/abteilungsleiter/stundeneintrag/${entry.EintragID}`)
    const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
    if (subIndex !== -1) {
      submissions.value[subIndex].details = submissions.value[subIndex].details.filter(e => e.EintragID !== entry.EintragID)
      recalculateTotal(submissionId)
    }
  } catch (error: any) {
    alert("Löschen fehlgeschlagen: " + error.message)
  }
}

function openAddDialog(submissionId: number) { isEditMode.value = false; currentSubmissionId.value = submissionId; currentEntryId.value = null; formData.value = { datum: new Date().toISOString().split('T')[0], beginn: '', ende: '', kurs: '' }; showDialog.value = true }

function openEditDialog(entry: TimesheetEntry, submissionId: number) {
  isEditMode.value = true;
  currentSubmissionId.value = submissionId;
  const id = entry.EintragID || (entry as any).id;
  currentEntryId.value = id;
  if (!id) { alert("Fehler: Keine ID gefunden."); return; }

  // Datum/Zeit für das Formular (input type="date/time") muss ISO bleiben
  const rawDate = entry.datum || '';
  const isoDate = rawDate.length > 10 ? rawDate.substring(0, 10) : rawDate;
  const isoTimeBegin = (entry.beginn && entry.beginn.length >= 5) ? entry.beginn.substring(0, 5) : '';
  const isoTimeEnd = (entry.ende && entry.ende.length >= 5) ? entry.ende.substring(0, 5) : '';

  formData.value = {
    datum: isoDate,
    beginn: isoTimeBegin,
    ende: isoTimeEnd,
    kurs: entry.kurs || ''
  }
  originalData.value = JSON.parse(JSON.stringify(formData.value))
  showDialog.value = true
}

async function saveEntry() {
  const { valid } = await dialogForm.value?.validate() || { valid: false }
  if (!valid) return
  dialogLoading.value = true
  try {
    const payload = { ...formData.value, fk_abrechnungID: currentSubmissionId.value, status_id: 11 }
    if (isEditMode.value) {
      if (!currentEntryId.value) throw new Error("ID fehlt.");
      await axios.put(`${API_BASE}/abteilungsleiter/stundeneintrag/${currentEntryId.value}`, payload)
      alert("Eintrag aktualisiert")
    } else {
      await axios.post(`${API_BASE}/abteilungsleiter/stundeneintrag`, payload)
      alert("Eintrag hinzugefügt")
    }
    await fetchReleaseSubmissions()
    showDialog.value = false
  } catch (error: any) {
    let msg = error.response?.data?.message || error.message || 'Fehler'
    alert(msg)
  } finally {
    dialogLoading.value = false
  }
}

function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) expandedIds.value = expandedIds.value.filter(x => x !== id)
  else expandedIds.value.push(id)
}
const requiredRule = [(v: any) => !!v || 'Pflichtfeld']

onMounted(() => {
  fetchReleaseSubmissions()
})
</script>

<template>
  <div class="page">
    <div class="d-flex justify-start mb-4">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="goBack">
        Zurück zum Dashboard
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4">
      <v-card-title class="pa-0 mb-4 d-flex align-center justify-space-between">
        <h3 class="ma-0">Abrechnungen freigeben</h3>
        <v-btn size="small" variant="text" color="primary" :loading="isLoading" @click="fetchReleaseSubmissions">
          Aktualisieren
        </v-btn>
      </v-card-title>

      <div class="text-body-2 text-medium-emphasis mb-4">
        In diesem Bereich gibst du als Abteilungsleiter die Abrechnungen für deine Abteilung frei.
      </div>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2"></v-progress-circular>
          Daten werden geladen ...
        </div>

        <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

        <div v-else-if="submissions.length > 0" class="list">
          <div v-for="item in submissions" :key="item.AbrechnungID" class="submission-wrapper">

            <div class="submission-row" @click="toggleDetails(item.AbrechnungID)">
              <div class="submission-main">
                <div class="line">
                  <span class="label">Übungsleiter:</span>
                  <span class="value font-weight-bold">{{ item.mitarbeiterName }}</span>
                </div>
                <div class="line">
                  <span class="label">Zeitraum:</span>
                  <span class="font-weight-bold">{{ item.quartal }}</span>
                  <span class="text-caption text-medium-emphasis ms-2">({{ item.zeitraum }})</span>
                </div>

                <div class="line">
                  <span class="label">Gesamt:</span>
                  <span class="value font-weight-bold">
                    {{ formatNumber(item.stunden) }} Std.
                  </span>
                  <span class="mx-2 text-medium-emphasis">|</span>
                  <span class="value font-weight-bold text-success">
                    {{ formatCurrency(item.gesamtBetrag) }}
                  </span>
                </div>
              </div>

              <div class="submission-actions">
                <v-icon :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'" class="mr-4 text-medium-emphasis"></v-icon>
                <v-btn size="small" color="error" variant="text" class="mr-2" @click.stop="openRejectDialog(item.AbrechnungID)" prepend-icon="mdi-close">Ablehnen</v-btn>
                <v-btn size="small" color="success" variant="flat" :loading="isProcessingId === item.AbrechnungID" :disabled="isProcessingId !== null" @click.stop="approveSubmission(item.AbrechnungID)" prepend-icon="mdi-check">Freigeben</v-btn>
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedIds.includes(item.AbrechnungID)" class="details-container">
                <v-table density="compact" class="bg-transparent mb-2">
                  <thead>
                  <tr>
                    <th class="text-left">Datum</th>
                    <th class="text-left">Zeit</th>
                    <th class="text-left">Kurs/Info</th>
                    <th class="text-right">Dauer</th>
                    <th class="text-right">Betrag</th>
                    <th class="text-right" style="width: 100px;">Aktionen</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr
                      v-for="(detail) in item.details"
                      :key="detail.EintragID"
                      :class="{ 'bg-orange-lighten-5': detail.isFeiertag }"
                  >

                    <td>{{ formatDate(detail.datum) }}
                      <span v-if="detail.isFeiertag" class="text-caption text-orange-darken-2 ml-1 font-weight-bold">
      (Feiertag)
    </span></td>


                    <td>{{ formatTime(detail.beginn) }} - {{ formatTime(detail.ende) }}</td>

                    <td>
                      {{ detail.kurs }}
                    </td>

                    <td class="text-right">{{ formatNumber(detail.dauer) }} Std.</td>

                    <td class="text-right font-weight-medium" :class="{ 'text-orange-darken-2': detail.isFeiertag }">
                      {{ formatCurrency(detail.betrag) }}
                    </td>

                    <td class="text-right">
                      <v-btn icon="mdi-pencil" size="x-small" variant="text" color="blue" class="mr-1" @click="openEditDialog(detail, item.AbrechnungID)"></v-btn>
                      <v-btn icon="mdi-delete" size="x-small" variant="text" color="red" @click="deleteEntry(detail, item.AbrechnungID)"></v-btn>
                    </td>
                  </tr>
                  </tbody>
                </v-table>
                <div class="d-flex justify-start">
                  <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" color="blue-grey" @click="openAddDialog(item.AbrechnungID)">Stunde hinzufügen</v-btn>
                </div>
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

    <v-dialog v-model="showDialog" max-width="500px">
      <v-card>
        <v-card-title>{{ isEditMode ? 'Eintrag bearbeiten' : 'Neuen Eintrag hinzufügen' }}</v-card-title>
        <v-card-text>
          <v-form ref="dialogForm" @submit.prevent="saveEntry">
            <v-text-field v-model="formData.datum" label="Datum" type="date" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-3"></v-text-field>
            <div class="d-flex" style="gap: 12px;">
              <v-text-field v-model="formData.beginn" label="Beginn" type="time" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-3"></v-text-field>
              <v-text-field v-model="formData.ende" label="Ende" type="time" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-3"></v-text-field>
            </div>
            <v-text-field v-model="formData.kurs" label="Kurs / Tätigkeit" variant="outlined" density="comfortable" class="mb-3"></v-text-field>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="showDialog = false">Abbrechen</v-btn>
          <v-btn color="primary" variant="flat" :loading="dialogLoading" :disabled="!hasChanges" @click="saveEntry">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="showRejectDialog" max-width="500px">
      <v-card>
        <v-card-title class="text-error">Abrechnung ablehnen</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-4">Bitte gib eine Begründung an.</p>
          <v-textarea v-model="rejectReason" label="Begründung" variant="outlined" auto-grow rows="3" :rules="[v => !!v || 'Erforderlich']"></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="showRejectDialog = false">Abbrechen</v-btn>
          <v-btn color="error" variant="flat" :loading="rejectLoading" @click="submitRejection">Ablehnen</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 800px; margin: 0 auto; }
.placeholder { min-height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(0, 0, 0, 0.6); font-size: 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); margin-top: 8px; padding: 16px; text-align: center; }
.list { margin-top: 8px; display: flex; flex-direction: column; gap: 12px; }
.submission-wrapper { background: white; border: 1px solid rgba(0,0,0,0.12); border-radius: 8px; overflow: hidden; transition: box-shadow 0.2s; }
.submission-wrapper:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
.submission-row { display: flex; justify-content: space-between; align-items: center; padding: 16px; cursor: pointer; }
.submission-row:hover { background-color: #f9f9f9; }
.submission-main { flex: 1; }
.line { display: flex; gap: 8px; margin-bottom: 6px; }
.line:last-child { margin-bottom: 0; }
.label { min-width: 120px; font-weight: 500; color: rgba(0, 0, 0, 0.6); font-size: 0.9rem; }
.value { font-weight: 400; color: rgba(0, 0, 0, 0.87); }
.submission-actions { display: flex; align-items: center; margin-left: 24px; }
.details-container { background-color: #fafafa; border-top: 1px solid rgba(0,0,0,0.06); padding: 16px; }
</style>