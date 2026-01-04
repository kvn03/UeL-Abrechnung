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

const API_BASE = 'http://127.0.0.1:8000/api/geschaeftsstelle'
const API_URL = `${API_BASE}/auszahlungen`

interface TimesheetEntry {
  datum: string
  dauer: number
  kurs: string | null
  betrag?: number | null
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  abteilung: string
  zeitraum: string
  stunden: number
  iban?: string
  gesamtBetrag?: number

  // NEU: Differenzierte Genehmigungsdaten
  datumGenehmigtAL: string
  genehmigtDurchAL: string
  datumFreigabeGS: string
  freigabeDurchGS: string

  details: TimesheetEntry[]
}

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const submissions = ref<Submission[]>([])
const isProcessingId = ref<number | null>(null)
const expandedIds = ref<number[]>([])

// --- Helper ---
function formatCurrency(val: number | undefined | null) {
  if (val === undefined || val === null) return '-'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// --- Laden ---
async function fetchSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedIds.value = []

  try {
    const response = await axios.get<Submission[]>(API_URL)
    submissions.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Auszahlungen.'
  } finally {
    isLoading.value = false
  }
}

// --- Bezahlen ---
async function markAsPaid(id: number) {
  if (!confirm('Wurde die Überweisung getätigt? Die Abrechnung wird archiviert (Status 23).')) return
  isProcessingId.value = id
  try {
    await axios.post(`${API_BASE}/abrechnungen/${id}/finalize`)
    submissions.value = submissions.value.filter(s => s.AbrechnungID !== id)
  } catch (error: any) {
    alert('Fehler: ' + (error.response?.data?.message || 'Konnte nicht verarbeitet werden.'))
  } finally {
    isProcessingId.value = null
  }
}

// --- PDF Generierung (NEU MIT FREIGABE-INFOS) ---
function generatePDF(item: Submission) {
  const doc = new jsPDF()

  // Titel
  doc.setFontSize(18)
  doc.text('Zahlungsanweisung', 14, 20)

  // Block: Basisdaten
  doc.setFontSize(11)
  doc.setTextColor(50)

  let yPos = 35;
  const lineHeight = 7;

  doc.text(`Übungsleiter: ${item.mitarbeiterName}`, 14, yPos); yPos += lineHeight;
  doc.text(`Abteilung: ${item.abteilung}`, 14, yPos); yPos += lineHeight;
  doc.text(`Zeitraum: ${item.zeitraum}`, 14, yPos); yPos += lineHeight + 4; // Bissel Abstand

  // Block: Freigaben (Neu)
  doc.setFontSize(10)
  doc.setTextColor(80) // Etwas grauer
  doc.text(`Genehmigt (Abteilungsleiter): ${item.datumGenehmigtAL} durch ${item.genehmigtDurchAL}`, 14, yPos); yPos += lineHeight;
  doc.text(`Freigabe (Geschäftsstelle): ${item.datumFreigabeGS} durch ${item.freigabeDurchGS}`, 14, yPos); yPos += lineHeight + 4;

  // Block: Zahlungsinfos
  doc.setFontSize(12)
  doc.setTextColor(0) // Schwarz zurück
  doc.setFont('helvetica', 'bold')
  doc.text(`IBAN: ${item.iban || 'Nicht hinterlegt'}`, 14, yPos); yPos += lineHeight;

  const betragText = item.gesamtBetrag
      ? item.gesamtBetrag.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
      : '0,00 €';
  doc.text(`Auszahlungsbetrag: ${betragText}`, 14, yPos); yPos += lineHeight + 4;
  doc.setFont('helvetica', 'normal')

  // Tabelle
  const tableData = item.details.map(d => [
    formatDate(d.datum),
    d.kurs || '-',
    d.dauer.toLocaleString('de-DE') + ' Std.',
    d.betrag ? d.betrag.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }) : '-'
  ])

  autoTable(doc, {
    startY: yPos,
    head: [['Datum', 'Kurs', 'Dauer', 'Betrag']],
    body: tableData,
    theme: 'grid',
    headStyles: { fillColor: [41, 128, 185] },
    columnStyles: { 3: { halign: 'right' } }
  })

  // Footer: Beleg ID
  const finalY = (doc as any).lastAutoTable.finalY || 150
  doc.setFontSize(9)
  doc.setTextColor(150)
  doc.text(`Beleg-ID: #${item.AbrechnungID} | Erstellt am: ${new Date().toLocaleDateString('de-DE')}`, 14, finalY + 10)

  doc.save(`Auszahlung_${item.mitarbeiterName.replace(/\s/g, '_')}_${item.zeitraum}.pdf`)
}

function toggleDetails(id: number) {
  if (expandedIds.value.includes(id)) expandedIds.value = expandedIds.value.filter(x => x !== id)
  else expandedIds.value.push(id)
}

onMounted(() => {
  fetchSubmissions()
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
      <v-card-title class="pa-0 mb-4 d-flex align-center justify-space-between">
        <div>
          <h3 class="ma-0">Offene Auszahlungen</h3>
          <div class="text-caption text-medium-emphasis">
            Status: Genehmigt (22) &rarr; Warten auf Überweisung
          </div>
        </div>
        <v-btn size="small" variant="text" color="primary" :loading="isLoading" @click="fetchSubmissions">
          Aktualisieren
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <div v-if="isLoading" class="placeholder">
          <v-progress-circular indeterminate color="primary" class="mb-2" />
          Lade Daten ...
        </div>

        <v-alert v-else-if="errorMessage" type="error" variant="tonal" class="mb-4">
          {{ errorMessage }}
        </v-alert>

        <div v-else-if="submissions.length > 0" class="list">
          <div v-for="item in submissions" :key="item.AbrechnungID" class="submission-wrapper">

            <div class="submission-row" @click="toggleDetails(item.AbrechnungID)">
              <div class="submission-main">
                <div class="d-flex justify-space-between mb-1">
                  <span class="text-h6 font-weight-bold text-primary">{{ item.mitarbeiterName }}</span>
                  <v-chip size="small" color="orange-darken-1" variant="flat">Zahlung offen</v-chip>
                </div>

                <div class="d-flex flex-wrap gap-4 info-grid">
                  <div class="line"><span class="label">Abt:</span> <span class="value">{{ item.abteilung }}</span></div>
                  <div class="line"><span class="label">Zeit:</span> <span class="value">{{ item.zeitraum }}</span></div>
                  <div class="line w-100 mt-1 pt-1 border-top" v-if="item.iban">
                    <span class="label">IBAN:</span> <span class="value font-monospace">{{ item.iban }}</span>
                  </div>
                  <div class="line mt-1">
                    <span class="label">Gesamt:</span>
                    <span class="value font-weight-bold text-h6 text-success">{{ formatCurrency(item.gesamtBetrag) }}</span>
                  </div>
                </div>
              </div>

              <div class="submission-actions">
                <v-btn icon="mdi-file-pdf-box" color="red-darken-1" variant="text" size="large" @click.stop="generatePDF(item)" title="PDF mit Freigabe-Info"></v-btn>
                <v-btn color="success" variant="elevated" prepend-icon="mdi-bank-transfer" :loading="isProcessingId === item.AbrechnungID" @click.stop="markAsPaid(item.AbrechnungID)">
                  Bezahlt
                </v-btn>
                <v-icon :icon="expandedIds.includes(item.AbrechnungID) ? 'mdi-chevron-up' : 'mdi-chevron-down'" class="ml-2 text-medium-emphasis" />
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedIds.includes(item.AbrechnungID)" class="details-container">
                <v-table density="compact" class="bg-transparent">
                  <thead>
                  <tr>
                    <th class="text-left">Datum</th>
                    <th class="text-left">Info</th>
                    <th class="text-right">Dauer</th>
                    <th class="text-right">Betrag</th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr v-for="(d, i) in item.details" :key="i">
                    <td>{{ formatDate(d.datum) }}</td>
                    <td>{{ d.kurs || '-' }}</td>
                    <td class="text-right">{{ d.dauer.toLocaleString('de-DE') }} Std.</td>
                    <td class="text-right font-weight-medium text-green-darken-2">{{ formatCurrency(d.betrag) }}</td>
                  </tr>
                  </tbody>
                </v-table>
              </div>
            </v-expand-transition>
          </div>
        </div>

        <div v-else class="placeholder">
          <v-icon icon="mdi-check-all" size="large" color="green" class="mb-2" />
          Keine offenen Zahlungen gefunden.
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 900px; margin: 0 auto; }
.border-t-lg-green { border-top: 4px solid #43A047; }
.placeholder { min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.02); border-radius: 8px; margin-top: 8px; }
.list { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.submission-wrapper { background: white; border: 1px solid rgba(0,0,0,0.12); border-radius: 8px; overflow: hidden; }
.submission-row { padding: 16px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.submission-row:hover { background-color: #f9f9f9; }
.submission-main { flex: 1; }
.info-grid { display: flex; flex-wrap: wrap; column-gap: 24px; row-gap: 4px; }
.line { display: flex; gap: 8px; font-size: 0.9rem; align-items: center; }
.border-top { border-top: 1px dashed rgba(0,0,0,0.1); }
.label { color: rgba(0,0,0,0.6); font-weight: 500; }
.font-monospace { font-family: monospace; letter-spacing: 0.5px; }
.submission-actions { display: flex; align-items: center; margin-left: 16px; gap: 8px; }
.details-container { background-color: #fafafa; border-top: 1px solid rgba(0,0,0,0.06); padding: 16px; }
</style>