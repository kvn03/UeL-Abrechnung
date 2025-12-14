<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_BASE = 'http://127.0.0.1:8000/api'
const API_URL = `${API_BASE}/abteilungsleiter/abrechnungen`

// --- TYPES ---
interface TimesheetEntry {
  EintragID: number // WICHTIG: ID wird für Edit/Delete benötigt
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
  datumEingereicht: string
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

// Wir merken uns, zu welcher Abrechnung wir gerade etwas hinzufügen/bearbeiten
const currentSubmissionId = ref<number | null>(null)
const currentEntryId = ref<number | null>(null) // Nur bei Edit

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

// --- HELPER: Summe neu berechnen ---
// Wenn wir Zeilen ändern/löschen, muss die Gesamtsumme der Abrechnung aktualisiert werden
function recalculateTotal(submissionId: number) {
  const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
  if (subIndex === -1) return

  const total = submissions.value[subIndex].details.reduce((sum, entry) => sum + Number(entry.dauer), 0)
  submissions.value[subIndex].stunden = parseFloat(total.toFixed(2))
}

// --- AKTION: LÖSCHEN ---
async function deleteEntry(entry: TimesheetEntry, submissionId: number) {
  if (!confirm(`Eintrag vom ${entry.datum} wirklich löschen?`)) return

  try {
    await axios.delete(`${API_BASE}/abteilungsleiter/stundeneintrag/${entry.EintragID}`)

    // Lokal entfernen
    const subIndex = submissions.value.findIndex(s => s.AbrechnungID === submissionId)
    if (subIndex !== -1) {
      submissions.value[subIndex].details = submissions.value[subIndex].details.filter(e => e.EintragID !== entry.EintragID)
      recalculateTotal(submissionId)
    }
  } catch (error: any) {
    alert("Löschen fehlgeschlagen: " + error.message)
  }
}

// --- AKTION: ÖFFNEN (Hinzufügen) ---
function openAddDialog(submissionId: number) {
  isEditMode.value = false
  currentSubmissionId.value = submissionId
  currentEntryId.value = null

  // Formular reset
  formData.value = {
    datum: new Date().toISOString().split('T')[0], // Heute
    beginn: '',
    ende: '',
    kurs: ''
  }
  showDialog.value = true
}

// --- AKTION: ÖFFNEN (Bearbeiten) ---
// --- AKTION: ÖFFNEN (Bearbeiten) ---
function openEditDialog(entry: TimesheetEntry, submissionId: number) {
  // Debugging: Schau in die Browser-Konsole (F12), was hier ausgegeben wird
  console.log("Öffne Edit für:", entry);

  isEditMode.value = true
  currentSubmissionId.value = submissionId

  // WICHTIG: Wir prüfen auf 'EintragID' UND 'id'.
  // Laravel sendet manchmal 'id' im JSON, auch wenn die DB-Spalte anders heißt.
  const id = entry.EintragID || (entry as any).id;
  currentEntryId.value = id;

  if (!id) {
    alert("Fehler: Keine ID für diesen Eintrag gefunden. Bearbeiten wird nicht funktionieren.");
    console.error("ID fehlt im Objekt:", entry);
  }

  // Datum und Zeit sicher formatieren
  // Datum auf YYYY-MM-DD kürzen (ersten 10 Zeichen)
  const rawDate = entry.datum || '';
  const formattedDate = rawDate.length > 10 ? rawDate.substring(0, 10) : rawDate;

  // Zeit auf HH:mm kürzen (ersten 5 Zeichen)
  const formatTime = (t: string) => (t && t.length >= 5) ? t.substring(0, 5) : '';

  formData.value = {
    datum: formattedDate,
    beginn: formatTime(entry.beginn),
    ende: formatTime(entry.ende),
    kurs: entry.kurs || ''
  }

  showDialog.value = true
}

// --- AKTION: SPEICHERN (Create / Update) ---
// --- AKTION: SPEICHERN (Create / Update) ---
async function saveEntry() {
  const { valid } = await dialogForm.value?.validate() || { valid: false }
  if (!valid) return

  dialogLoading.value = true

  try {
    const payload = {
      ...formData.value,
      fk_abrechnungID: currentSubmissionId.value,
      status_id: 11 // Status: Eingereicht / In Prüfung
    }

    // Entscheidung: Update oder Neu?
    if (isEditMode.value) {
      // SICHERHEITSPRÜFUNG
      if (!currentEntryId.value) {
        throw new Error("Fehler: ID verloren gegangen. Kann nicht speichern.");
      }

      // UPDATE (PUT)
      await axios.put(`${API_BASE}/abteilungsleiter/stundeneintrag/${currentEntryId.value}`, payload)
      alert("Eintrag erfolgreich aktualisiert")
    } else {
      // CREATE (POST)
      await axios.post(`${API_BASE}/abteilungsleiter/stundeneintrag`, payload)
      alert("Eintrag erfolgreich hinzugefügt")
    }

    // Liste neu laden
    await fetchReleaseSubmissions()
    showDialog.value = false

  } catch (error: any) {
    console.error(error)
    let msg = error.response?.data?.message || error.message || 'Fehler beim Speichern'
    if (error.response?.data?.errors) {
      msg += '\n' + JSON.stringify(error.response.data.errors)
    }
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

// Validation Rules
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

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2"></v-progress-circular>
          Daten werden geladen ...
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
              </div>

              <div class="submission-actions">
                <v-icon :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'" class="mr-4 text-medium-emphasis"></v-icon>
                <v-btn
                    size="small"
                    color="success"
                    variant="flat"
                    :loading="isProcessingId === item.AbrechnungID"
                    :disabled="isProcessingId !== null"
                    @click.stop="approveSubmission(item.AbrechnungID)"
                    prepend-icon="mdi-check"
                >
                  Freigeben
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
                  <tr v-for="(detail) in item.details" :key="detail.EintragID">
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
          Aktuell keine offenen Freigaben vorhanden.
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
            <v-text-field
                v-model="formData.datum"
                label="Datum"
                type="date"
                variant="outlined"
                density="comfortable"
                :rules="requiredRule"
                class="mb-3"
            ></v-text-field>

            <div class="d-flex" style="gap: 12px;">
              <v-text-field
                  v-model="formData.beginn"
                  label="Beginn"
                  type="time"
                  variant="outlined"
                  density="comfortable"
                  :rules="requiredRule"
                  class="mb-3"
              ></v-text-field>
              <v-text-field
                  v-model="formData.ende"
                  label="Ende"
                  type="time"
                  variant="outlined"
                  density="comfortable"
                  :rules="requiredRule"
                  class="mb-3"
              ></v-text-field>
            </div>

            <v-text-field
                v-model="formData.kurs"
                label="Kurs / Tätigkeit"
                variant="outlined"
                density="comfortable"
                class="mb-3"
            ></v-text-field>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="showDialog = false">Abbrechen</v-btn>
          <v-btn
              color="primary"
              variant="flat"
              :loading="dialogLoading"
              @click="saveEntry"
          >
            Speichern
          </v-btn>
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
.line:last-child { margin-bottom: 0; }

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