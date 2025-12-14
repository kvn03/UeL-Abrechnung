<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

// [BACKEND] API Base URL
const API_BASE = 'http://127.0.0.1:8000/api'
// WICHTIG: Hier zeigen wir auf den Geschäftsstellen-Controller
const API_URL = `${API_BASE}/geschaeftsstelle/abrechnungen`

// --- TYPES ---
interface TimesheetEntry {
  EintragID: number
  datum: string
  beginn: string
  ende: string
  dauer: number
  kurs: string
  fk_abrechnungID?: number
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  zeitraum: string
  stunden: number
  // GS spezifische Felder aus dem Controller:
  datumGenehmigtAL: string
  genehmigtDurch: string
  details: TimesheetEntry[]
}

// --- STATE ---
const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)
const expandedIds = ref<number[]>([])

// --- DIALOG STATE (Bearbeiten / Hinzufügen) ---
const showDialog = ref(false)
const isEditMode = ref(false)
const dialogLoading = ref(false)
const dialogForm = ref<VForm | null>(null)

// Referenzen für Edit/Add
const currentSubmissionId = ref<number | null>(null)
const currentEntryId = ref<number | null>(null)

// Formular-Daten
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
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der GS-Abrechnungen.'
  } finally {
    isLoading.value = false
  }
}

// --- API: FREIGEBEN (FINAL) ---
async function approveSubmission(id: number) {
  if (!confirm("Möchtest du diese Abrechnung final freigeben und zur Auszahlung anweisen?")) return
  isProcessingId.value = id

  try {
    // Ruft 'finalize' im GeschaeftsstelleController auf
    await axios.post(`${API_URL}/${id}/finalize`)

    // Erfolgreich -> Aus Liste entfernen
    submissions.value = submissions.value.filter(item => item.AbrechnungID !== id)
  } catch (error: any) {
    alert("Fehler: " + (error.response?.data?.message || "Unbekannter Fehler"))
  } finally {
    isProcessingId.value = null
  }
}

// --- AKTION: LÖSCHEN (GS) ---
async function deleteEntry(entry: TimesheetEntry, submissionId: number) {
  if (!confirm(`Eintrag vom ${entry.datum} wirklich löschen?`)) return

  try {
    // WICHTIG: Pfad zum GS Controller
    await axios.delete(`${API_BASE}/geschaeftsstelle/stundeneintrag/${entry.EintragID}`)

    // Lokal entfernen & Summe neu berechnen
    const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
    if (subIndex !== -1) {
      submissions.value[subIndex].details = submissions.value[subIndex].details.filter(e => e.EintragID !== entry.EintragID)
      recalculateTotal(submissionId)
    }
  } catch (error: any) {
    alert("Löschen fehlgeschlagen: " + (error.response?.data?.message || error.message))
  }
}

// --- HELPER: Summe neu berechnen ---
function recalculateTotal(submissionId: number) {
  const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
  if (subIndex === -1) return

  const total = submissions.value[subIndex].details.reduce((sum, entry) => sum + Number(entry.dauer), 0)
  submissions.value[subIndex].stunden = parseFloat(total.toFixed(2))
}

// --- AKTION: ÖFFNEN (Hinzufügen) ---
function openAddDialog(submissionId: number) {
  isEditMode.value = false
  currentSubmissionId.value = submissionId
  currentEntryId.value = null

  // Reset Form
  formData.value = {
    datum: new Date().toISOString().split('T')[0],
    beginn: '',
    ende: '',
    kurs: ''
  }
  showDialog.value = true
}

// --- AKTION: ÖFFNEN (Bearbeiten) ---
function openEditDialog(entry: TimesheetEntry, submissionId: number) {
  isEditMode.value = true
  currentSubmissionId.value = submissionId

  // ID-Fix: Fallback falls 'id' klein geschrieben kommt
  const id = entry.EintragID || (entry as any).id;
  currentEntryId.value = id;

  if (!id) {
    alert("Fehler: Keine ID gefunden. Bearbeiten nicht möglich.");
    return;
  }

  // Datum/Zeit Formatierung
  const rawDate = entry.datum || '';
  const formattedDate = rawDate.length > 10 ? rawDate.substring(0, 10) : rawDate;
  const formatTime = (t: string) => (t && t.length >= 5) ? t.substring(0, 5) : '';

  formData.value = {
    datum: formattedDate,
    beginn: formatTime(entry.beginn),
    ende: formatTime(entry.ende),
    // "-" entfernen, falls vom Backend kommend
    kurs: (entry.kurs === '-' || !entry.kurs) ? '' : entry.kurs
  }

  showDialog.value = true
}

// --- AKTION: SPEICHERN (Create / Update via GS Controller) ---
async function saveEntry() {
  const { valid } = await dialogForm.value?.validate() || { valid: false }
  if (!valid) return

  dialogLoading.value = true

  try {
    const payload = {
      ...formData.value,
      fk_abrechnungID: currentSubmissionId.value,
      status_id: 21 // Wir lassen den Status auf "AL Genehmigt", bis GS final klickt
    }

    if (isEditMode.value) {
      if (!currentEntryId.value) throw new Error("ID fehlt");
      // Update via GS Route
      await axios.put(`${API_BASE}/geschaeftsstelle/stundeneintrag/${currentEntryId.value}`, payload)
      alert("Eintrag aktualisiert")
    } else {
      // Create via GS Route
      await axios.post(`${API_BASE}/geschaeftsstelle/stundeneintrag`, payload)
      alert("Eintrag hinzugefügt")
    }

    await fetchReleaseSubmissions()
    showDialog.value = false

  } catch (error: any) {
    console.error(error)
    let msg = error.response?.data?.message || 'Fehler beim Speichern'
    alert(msg)
  } finally {
    dialogLoading.value = false
  }
}

// Toggle logic
function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) {
    expandedIds.value = expandedIds.value.filter(x => x !== id)
  } else {
    expandedIds.value.push(id)
  }
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

    <v-card elevation="6" class="pa-4 border-t-lg-green">
      <v-card-title class="pa-0 mb-2 d-flex align-center justify-space-between">
        <h3 class="ma-0">Finale Freigabe (Geschäftsstelle)</h3>
        <v-btn size="small" variant="text" color="primary" :loading="isLoading" @click="fetchReleaseSubmissions">
          Aktualisieren
        </v-btn>
      </v-card-title>

      <div class="text-body-2 text-medium-emphasis mb-4" style="max-width: 90%;">
        Hier erscheinen alle Abrechnungen, die bereits von den Abteilungsleitern geprüft und genehmigt wurden.
        Bitte prüfen Sie die Angaben und erteilen Sie die finale Freigabe zur Auszahlung.
      </div>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2"></v-progress-circular>
          Lade Daten ...
        </div>

        <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="submissions.length > 0" class="list">
          <div v-for="item in submissions" :key="item.AbrechnungID" class="submission-wrapper">

            <div class="submission-row" @click="toggleDetails(item.AbrechnungID)">
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
                  <span class="label">Gesamt:</span>
                  <span class="value font-weight-bold text-primary">
                    {{ item.stunden.toLocaleString('de-DE') }} Std.
                  </span>
                </div>

                <div class="line mt-2 pt-2 border-top">
                  <span class="label text-caption">Genehmigt am:</span>
                  <span class="value text-caption">
                    {{ item.datumGenehmigtAL }} <span class="text-medium-emphasis">von</span> {{ item.genehmigtDurch }}
                  </span>
                </div>
              </div>

              <div class="submission-actions">
                <v-icon :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'" class="mr-4 text-medium-emphasis"></v-icon>

                <v-btn
                    size="small"
                    color="green-darken-1"
                    variant="flat"
                    :loading="isProcessingId === item.AbrechnungID"
                    :disabled="isProcessingId !== null"
                    @click.stop="approveSubmission(item.AbrechnungID)"
                    prepend-icon="mdi-check-all"
                >
                  Final
                </v-btn>
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
                    <th class="text-right" style="width: 100px;">Aktionen</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="detail in item.details" :key="detail.EintragID">
                    <td>{{ detail.datum }}</td>
                    <td>{{ detail.beginn }} - {{ detail.ende }}</td>
                    <td>{{ detail.kurs }}</td>
                    <td class="text-right">{{ detail.dauer }} Std.</td>
                    <td class="text-right">
                      <v-btn
                          icon="mdi-pencil"
                          size="x-small"
                          variant="text"
                          color="blue"
                          class="mr-1"
                          @click="openEditDialog(detail, item.AbrechnungID)"
                      ></v-btn>
                      <v-btn
                          icon="mdi-delete"
                          size="x-small"
                          variant="text"
                          color="red"
                          @click="deleteEntry(detail, item.AbrechnungID)"
                      ></v-btn>
                    </td>
                  </tr>
                  </tbody>
                </v-table>

                <div class="d-flex justify-start">
                  <v-btn
                      size="small"
                      variant="tonal"
                      prepend-icon="mdi-plus"
                      color="blue-grey"
                      @click="openAddDialog(item.AbrechnungID)"
                  >
                    Stunde hinzufügen
                  </v-btn>
                </div>
              </div>
            </v-expand-transition>
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-check-all" size="large" color="success" class="mb-2"></v-icon>
          Aktuell keine offenen Freigaben für die Geschäftsstelle.
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="500px">
      <v-card>
        <v-card-title>
          {{ isEditMode ? 'Eintrag bearbeiten' : 'Neuen Eintrag hinzufügen' }}
        </v-card-title>
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
          <v-btn color="primary" variant="flat" :loading="dialogLoading" @click="saveEntry">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 800px;
  margin: 0 auto;
}

/* Grüner Rand oben für visuelle Unterscheidung */
.border-t-lg-green {
  border-top: 4px solid #43A047;
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

.submission-wrapper {
  background: white;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  overflow: hidden;
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
  cursor: pointer;
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
.border-top {
  border-top: 1px solid rgba(0,0,0,0.08);
}

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

.details-container {
  background-color: #fafafa;
  border-top: 1px solid rgba(0,0,0,0.06);
  padding: 16px;
}
</style>