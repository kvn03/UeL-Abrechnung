<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

// Backend liefert hier alle Abrechnungen mit Status 22 (Genehmigt durch AL)
const API_URL = 'http://127.0.0.1:8000/api/geschaeftsstelle/abrechnungen'

interface TimesheetEntry {
  datum: string
  dauer: number
  kurs: string | null
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  mitarbeiterID: number
  abteilung: string
  zeitraum: string
  stunden: number
  datumGenehmigtAL: string
  genehmigtDurch: string
  // Neu für PDF und Zahlung:
  iban?: string
  betrag?: number // Falls das Backend den berechneten Euro-Betrag liefert
  details: TimesheetEntry[]
}

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)
const expandedIds = ref<number[]>([])

async function fetchSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedIds.value = []

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Abrechnungen.'
  } finally {
    isLoading.value = false
  }
}

// PDF Generierung
function generatePDF(item: Submission) {
  const doc = new jsPDF()

  // Titel
  doc.setFontSize(18)
  doc.text('Zahlungsanweisung / Abrechnungsbeleg', 14, 20)

  // Metadaten Header
  doc.setFontSize(12)
  doc.text(`Mitarbeiter: ${item.mitarbeiterName}`, 14, 35)
  doc.text(`Abteilung: ${item.abteilung}`, 14, 42)
  doc.text(`Zeitraum: ${item.zeitraum}`, 14, 49)

  // Wichtig: IBAN hervorheben
  doc.setFont('helvetica', 'bold')
  doc.text(`IBAN: ${item.iban || 'Keine IBAN hinterlegt'}`, 14, 60)

  if (item.betrag) {
    doc.text(`Auszahlungsbetrag: ${item.betrag.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })}`, 14, 67)
  } else {
    doc.text(`Gesamtstunden: ${item.stunden.toLocaleString('de-DE')}`, 14, 67)
  }
  doc.setFont('helvetica', 'normal')

  // Tabelle der Stunden
  const tableData = item.details.map(d => [
    new Date(d.datum).toLocaleDateString('de-DE'),
    d.kurs || 'Sonstiges',
    d.dauer.toLocaleString('de-DE') + ' Std.'
  ])

  autoTable(doc, {
    startY: 75,
    head: [['Datum', 'Tätigkeit/Kurs', 'Dauer']],
    body: tableData,
    theme: 'grid',
    headStyles: { fillColor: [41, 128, 185] },
  })

  // Footer info
  const finalY = (doc as any).lastAutoTable.finalY || 150
  doc.setFontSize(10)
  doc.setTextColor(100)
  doc.text(`Genehmigt am: ${item.datumGenehmigtAL} durch ${item.genehmigtDurch}`, 14, finalY + 10)
  doc.text(`Beleg-ID: #${item.AbrechnungID}`, 14, finalY + 16)

  // Download
  doc.save(`Auszahlung_${item.mitarbeiterName.replace(/\s/g, '_')}_${item.zeitraum}.pdf`)
}

// Statuswechsel von 22 -> 23
async function markAsPaid(id: number) {
  if (!confirm('Wurde die Überweisung getätigt? Status wird auf "Bezahlt" (23) gesetzt.')) return

  isProcessingId.value = id
  try {
    // Ruft Backend auf -> Setzt status_id = 23
    await axios.post(`${API_URL}/${id}/finalize`)

    // UI Update
    submissions.value = submissions.value.filter(s => s.AbrechnungID !== id)
  } catch (error: any) {
    alert('Fehler: ' + (error.response?.data?.message || 'Konnte nicht verarbeitet werden.'))
  } finally {
    isProcessingId.value = null
  }
}

function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) {
    expandedIds.value = expandedIds.value.filter(x => x !== id)
  } else {
    expandedIds.value.push(id)
  }
}

onMounted(() => {
  fetchSubmissions()
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
        <div>
          <h3 class="ma-0">Auszahlungen (Geschäftsstelle)</h3>
          <div class="text-caption text-medium-emphasis">
            Abrechnungen mit Status "Genehmigt" (22)
          </div>
        </div>
        <v-btn
            size="small"
            variant="text"
            color="primary"
            :loading="isLoading"
            @click="fetchSubmissions"
        >
          Aktualisieren
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2" />
          Lade Zahlungsdaten ...
        </div>

        <v-alert
            v-else-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
        >
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="submissions.length > 0" class="list">
          <div
              v-for="item in submissions"
              :key="item.AbrechnungID"
              class="submission-wrapper"
          >
            <div class="submission-row" @click="toggleDetails(item.AbrechnungID)">
              <div class="submission-main">
                <div class="d-flex justify-space-between mb-1">
                  <span class="text-h6 font-weight-bold text-primary">{{ item.mitarbeiterName }}</span>
                  <v-chip size="small" color="orange-darken-1" variant="flat">Status: Genehmigt (22)</v-chip>
                </div>

                <div class="d-flex flex-wrap gap-4 info-grid">
                  <div class="line">
                    <span class="label">Abteilung:</span>
                    <span class="value">{{ item.abteilung }}</span>
                  </div>
                  <div class="line">
                    <span class="label">Zeitraum:</span>
                    <span class="value">{{ item.zeitraum }}</span>
                  </div>
                  <div class="line" v-if="item.iban">
                    <span class="label">IBAN:</span>
                    <span class="value font-weight-bold font-monospace">{{ item.iban }}</span>
                  </div>
                  <div class="line" v-if="item.betrag">
                    <span class="label">Betrag:</span>
                    <span class="value font-weight-bold text-success">
                      {{ item.betrag.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }) }}
                    </span>
                  </div>
                  <div class="line" v-else>
                    <span class="label">Stunden:</span>
                    <span class="value font-weight-bold">{{ item.stunden }} Std.</span>
                  </div>
                </div>
              </div>

              <div class="submission-actions">
                <v-btn
                    icon="mdi-file-pdf-box"
                    color="red-darken-1"
                    variant="text"
                    size="large"
                    class="mr-2"
                    title="PDF herunterladen"
                    @click.stop="generatePDF(item)"
                ></v-btn>

                <v-btn
                    color="success"
                    variant="elevated"
                    prepend-icon="mdi-check-circle-outline"
                    :loading="isProcessingId === item.AbrechnungID"
                    :disabled="isProcessingId !== null"
                    @click.stop="markAsPaid(item.AbrechnungID)"
                >
                  Bezahlt (Abschließen)
                </v-btn>

                <v-icon
                    :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                    class="ml-2 text-medium-emphasis"
                />
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedIds.includes(item.AbrechnungID)" class="details-container">
                <div class="text-subtitle-2 mb-2 text-medium-emphasis">Detailansicht Stunden:</div>
                <v-table density="compact" class="bg-transparent">
                  <thead>
                  <tr>
                    <th class="text-left">Datum</th>
                    <th class="text-left">Info</th>
                    <th class="text-right">Dauer</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(d, i) in item.details" :key="i">
                    <td>{{ new Date(d.datum).toLocaleDateString('de-DE') }}</td>
                    <td>{{ d.kurs || '-' }}</td>
                    <td class="text-right">{{ d.dauer.toLocaleString('de-DE') }}</td>
                  </tr>
                  </tbody>
                </v-table>
              </div>
            </v-expand-transition>
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-check-all" size="large" color="green" class="mb-2" />
          Alles erledigt! Keine offenen Auszahlungen.
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  max-width: 900px;
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
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 8px;
}

.submission-wrapper {
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}

.submission-wrapper:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.submission-row {
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.submission-main {
  flex: 1;
}

.info-grid {
  display: flex;
  flex-wrap: wrap;
  column-gap: 24px;
  row-gap: 4px;
}

.line {
  display: flex;
  gap: 8px;
  font-size: 0.9rem;
  align-items: center;
}

.label {
  color: rgba(0, 0, 0, 0.6);
  font-weight: 500;
}

.value {
  color: rgba(0, 0, 0, 0.87);
}

.font-monospace {
  font-family: monospace;
  font-size: 1rem;
  letter-spacing: 0.5px;
}

.submission-actions {
  display: flex;
  align-items: center;
  margin-left: 16px;
}

.details-container {
  background-color: #fafafa;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  padding: 16px;
}

@media(max-width: 600px) {
  .submission-row {
    flex-direction: column;
    align-items: flex-start;
  }
  .submission-actions {
    margin-left: 0;
    margin-top: 16px;
    width: 100%;
    justify-content: space-between;
  }
}
</style>