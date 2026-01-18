<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const API_BASE = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle'
const API_URL = `${API_BASE}/auszahlungen`

// --- INTERFACES ---

interface TimesheetEntry {
  datum: string
  dauer: number
  kurs: string | null
  betrag?: number | null
  isFeiertag?: boolean // <--- NEU
}

interface Submission {
  AbrechnungID: number
  mitarbeiterName: string
  mitarbeiterID: number
  abteilung: string
  zeitraum: string
  stunden: number
  iban?: string
  gesamtBetrag?: number
  // Adressdaten
  strasse?: string
  hausnr?: string
  plz?: string
  ort?: string
  // Freigabe Infos
  datumGenehmigtAL: string
  genehmigtDurchAL: string
  datumFreigabeGS: string
  freigabeDurchGS: string
  details: TimesheetEntry[]
}

interface GroupedSubmission {
  mitarbeiterID: number
  mitarbeiterName: string
  iban: string
  strasse: string
  hausnr: string
  plz: string
  ort: string
  totalBetrag: number
  totalStunden: number
  items: Submission[]
}

// --- STATE ---

const isLoading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const rawSubmissions = ref<Submission[]>([])
const isProcessingGroup = ref<number | null>(null)
const isProcessingId = ref<number | null>(null)
const expandedGroupIds = ref<number[]>([])

// --- COMPUTED ---

const groupedSubmissions = computed(() => {
  const groups: Record<string, GroupedSubmission> = {}

  rawSubmissions.value.forEach(sub => {
    const mId = sub.mitarbeiterID ? sub.mitarbeiterID : 0

    if (!groups[mId]) {
      groups[mId] = {
        mitarbeiterID: mId,
        mitarbeiterName: sub.mitarbeiterName,
        iban: sub.iban || '',
        strasse: sub.strasse || '',
        hausnr: sub.hausnr || '',
        plz: sub.plz || '',
        ort: sub.ort || '',
        totalBetrag: 0,
        totalStunden: 0,
        items: []
      }
    }

    groups[mId].items.push(sub)

    const betrag = sub.gesamtBetrag ? Number(sub.gesamtBetrag) : 0
    const stunden = sub.stunden ? Number(sub.stunden) : 0

    groups[mId].totalBetrag += betrag
    groups[mId].totalStunden += stunden
  })

  return Object.values(groups)
})

// --- HELPER ---

function formatCurrency(val: number | undefined | null) {
  if (val === undefined || val === null) return '-'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// Prüft, ob in einer Abrechnung Feiertage enthalten sind
function hasHoliday(sub: Submission): boolean {
  return sub.details.some(d => d.isFeiertag === true)
}

// --- API ---

async function fetchSubmissions() {
  isLoading.value = true
  errorMessage.value = null
  expandedGroupIds.value = []

  try {
    const response = await axios.get<Submission[]>(API_URL)
    rawSubmissions.value = response.data
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Fehler beim Laden der Auszahlungen.'
  } finally {
    isLoading.value = false
  }
}

// --- PDF ACTIONS ---

function generateSinglePDF(item: Submission) {
  const doc = new jsPDF()
  doc.setFontSize(18); doc.text('Zahlungsanweisung', 14, 20)
  doc.setFontSize(11); doc.setTextColor(50); let yPos = 35;
  doc.setFont('helvetica', 'bold'); doc.text(`Übungsleiter: ${item.mitarbeiterName}`, 14, yPos); yPos += 6;
  doc.setFont('helvetica', 'normal');

  if (item.ort) {
    doc.setFontSize(10);
    doc.text(`${item.strasse || ''} ${item.hausnr || ''}`, 14, yPos); yPos += 5;
    doc.text(`${item.plz || ''} ${item.ort || ''}`, 14, yPos); yPos += 8;
    doc.setFontSize(11);
  } else { yPos += 4; }

  doc.text(`Abteilung: ${item.abteilung}`, 14, yPos); yPos += 6;
  doc.text(`Zeitraum: ${item.zeitraum}`, 14, yPos); yPos += 8;

  doc.setFontSize(10); doc.setTextColor(80);
  doc.text(`Genehmigt (Abteilungsleiter): ${item.datumGenehmigtAL} (${item.genehmigtDurchAL})`, 14, yPos); yPos += 5;
  doc.text(`Freigabe (Geschäftsstelle): ${item.datumFreigabeGS} (${item.freigabeDurchGS})`, 14, yPos); yPos += 8;

  doc.setFontSize(12); doc.setTextColor(0); doc.setFont('helvetica', 'bold');
  doc.text(`IBAN: ${item.iban || 'Nicht hinterlegt'}`, 14, yPos); yPos += 6;
  doc.text(`Betrag: ${formatCurrency(item.gesamtBetrag)}`, 14, yPos); yPos += 10;
  doc.setFont('helvetica', 'normal');

  // DATEN FÜR PDF AUFBEREITEN
  const tableData = item.details.map(d => {
    // Hinweis im PDF, wenn Feiertag
    const datumText = d.isFeiertag
        ? formatDate(d.datum) + ' (Feiertag)'
        : formatDate(d.datum);

    return [
      datumText,
      d.kurs || '-',
      d.dauer.toLocaleString('de-DE') + ' Std.',
      d.betrag ? formatCurrency(d.betrag) : '-'
    ]
  })

  autoTable(doc, {
    startY: yPos,
    head: [['Datum', 'Kurs', 'Dauer', 'Betrag']],
    body: tableData,
    theme: 'grid',
    columnStyles: { 3: { halign: 'right' } },
    // Feiertagszeilen im PDF hervorheben (optional)
    didParseCell: function(data) {
      if (data.section === 'body' && item.details[data.row.index].isFeiertag) {
        data.cell.styles.textColor = [200, 100, 0]; // Orange/Rot für Text
        data.cell.styles.fontStyle = 'bold';
      }
    }
  })

  const finalY = (doc as any).lastAutoTable.finalY || 150
  doc.setFontSize(9); doc.setTextColor(150);
  doc.text(`Beleg-ID: #${item.AbrechnungID} | Erstellt: ${new Date().toLocaleDateString('de-DE')}`, 14, finalY + 10)
  doc.save(`Abrechnung_${item.mitarbeiterName}_${item.zeitraum}.pdf`)
}

function generateGroupPDF(group: GroupedSubmission) {
  const doc = new jsPDF()
  doc.setFontSize(18); doc.text('Sammel-Zahlungsanweisung', 14, 20)
  doc.setFontSize(11); doc.setTextColor(50); let yPos = 35;
  doc.setFont('helvetica', 'bold'); doc.text(`Empfänger: ${group.mitarbeiterName}`, 14, yPos); yPos += 6;
  doc.setFont('helvetica', 'normal');

  if (group.ort) {
    doc.setFontSize(10);
    doc.text(`${group.strasse} ${group.hausnr}`, 14, yPos); yPos += 5;
    doc.text(`${group.plz} ${group.ort}`, 14, yPos); yPos += 8;
    doc.setFontSize(11);
  } else { yPos += 4; }

  doc.setFont('helvetica', 'bold'); doc.text(`IBAN: ${group.iban || 'Nicht hinterlegt'}`, 14, yPos); yPos += 7;
  doc.setFontSize(14); doc.setTextColor(0); doc.text(`Gesamtsumme: ${formatCurrency(group.totalBetrag)}`, 14, yPos);
  doc.setFontSize(11); doc.setTextColor(50); doc.setFont('helvetica', 'normal'); yPos += 10;

  const tableRows: any[] = []

  // Wir müssen uns merken, welche Zeile ein Feiertag ist, für das Styling
  const holidayRows: number[] = [];
  let rowIndex = 0;

  group.items.forEach(sub => {
    sub.details.forEach(d => {
      const datumText = d.isFeiertag
          ? formatDate(d.datum) + ' (Feiertag)'
          : formatDate(d.datum);

      if (d.isFeiertag) holidayRows.push(rowIndex);

      tableRows.push([
        datumText,
        `${sub.abteilung}: ${d.kurs || '-'}`,
        d.dauer.toLocaleString('de-DE') + ' Std.',
        d.betrag ? formatCurrency(Number(d.betrag)) : '-'
      ])
      rowIndex++;
    })
  })

  // Sortieren ist hier schwierig wegen der Zeilen-Indizes für Farben,
  // daher lassen wir es in der Reihenfolge der Abrechnungen oder sortieren vorher die Objekte.
  // Einfachheitshalber hier ohne Sortierung der Rohdaten im PDF-Generator

  autoTable(doc, {
    startY: yPos,
    head: [['Datum', 'Abteilung / Info', 'Dauer', 'Betrag']],
    body: tableRows,
    theme: 'grid',
    columnStyles: { 3: { halign: 'right' } },
    didParseCell: function(data) {
      // Da wir tableRows flach gemacht haben, ist data.row.index relativ zur Tabelle
      // Wir prüfen, ob der Index in unserer holidayRows Liste ist
      // (Achtung: autoTable sortiert evtl intern nicht, wenn wir body direkt übergeben)
      // Einfacher Check: Enthält der Text "(Feiertag)"?
      if (data.section === 'body' && String(data.row.raw[0]).includes('(Feiertag)')) {
        data.cell.styles.textColor = [200, 100, 0];
        data.cell.styles.fontStyle = 'bold';
      }
    }
  })

  const finalY = (doc as any).lastAutoTable.finalY || 150
  const idList = group.items.map(i => '#' + i.AbrechnungID).join(', ');
  doc.setFontSize(9); doc.setTextColor(150);
  doc.text(`Enthält Abrechnungen: ${idList}`, 14, finalY + 10); doc.text(`Erstellt: ${new Date().toLocaleDateString('de-DE')}`, 14, finalY + 15)
  doc.save(`Sammel_Auszahlung_${group.mitarbeiterName.replace(/\s/g, '_')}.pdf`)
}

// --- PAY ACTIONS ---

async function payGroup(group: GroupedSubmission) {
  const ids = group.items.map(i => i.AbrechnungID)
  if (!confirm(`Alles für ${group.mitarbeiterName} (${formatCurrency(group.totalBetrag)}) bezahlen?`)) return
  isProcessingGroup.value = group.mitarbeiterID
  try {
    await axios.post(`${API_BASE}/abrechnungen/finalize-bulk`, { ids: ids })
    rawSubmissions.value = rawSubmissions.value.filter(s => !ids.includes(s.AbrechnungID))
  } catch (error: any) {
    alert('Fehler: ' + (error.response?.data?.message || 'Fehler'))
  } finally {
    isProcessingGroup.value = null
  }
}

async function paySingle(item: Submission) {
  if (!confirm(`Abrechnung #${item.AbrechnungID} (${formatCurrency(item.gesamtBetrag)}) als bezahlt markieren?`)) return
  isProcessingId.value = item.AbrechnungID
  try {
    await axios.post(`${API_BASE}/abrechnungen/${item.AbrechnungID}/finalize`)
    rawSubmissions.value = rawSubmissions.value.filter(s => s.AbrechnungID !== item.AbrechnungID)
  } catch (error: any) {
    alert('Fehler: ' + (error.response?.data?.message || 'Fehler'))
  } finally {
    isProcessingId.value = null
  }
}

function toggleGroup(mId: number) {
  if (expandedGroupIds.value.includes(mId)) {
    expandedGroupIds.value = expandedGroupIds.value.filter(id => id !== mId)
  } else {
    expandedGroupIds.value.push(mId)
  }
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
            Hier erscheinen alle Abrechnungen, die den Freigabeprozess durchlaufen haben, aber noch
            nicht ausgezahlt wurden.
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

        <div v-else-if="groupedSubmissions.length > 0" class="d-flex flex-column gap-3">

          <div
              v-for="group in groupedSubmissions"
              :key="group.mitarbeiterID"
              class="group-card"
          >
            <div class="group-header pa-4 d-flex flex-wrap align-center justify-space-between" @click="toggleGroup(group.mitarbeiterID)">

              <div class="d-flex align-center">
                <v-avatar color="primary" variant="tonal" class="mr-3">
                  <span class="text-h6">{{ group.mitarbeiterName.charAt(0) }}</span>
                </v-avatar>
                <div>
                  <div class="text-h6 font-weight-bold">{{ group.mitarbeiterName }}</div>
                  <div class="text-caption text-medium-emphasis d-flex align-center">
                    <v-icon icon="mdi-file-document-multiple-outline" size="x-small" class="mr-1"></v-icon>
                    {{ group.items.length }} Abrechnung(en) offen
                  </div>
                </div>
              </div>

              <div class="d-flex align-center mt-2 mt-sm-0 gap-4">
                <div class="text-right mr-4">
                  <div class="text-caption text-medium-emphasis">Gesamtsumme</div>
                  <div class="text-h6 text-success font-weight-bold">{{ formatCurrency(group.totalBetrag) }}</div>
                </div>

                <div class="d-flex gap-2">
                  <v-btn
                      icon="mdi-file-pdf-box"
                      color="blue-grey"
                      variant="text"
                      title="Sammel-PDF erstellen"
                      @click.stop="generateGroupPDF(group)"
                  ></v-btn>

                  <v-btn
                      color="success"
                      variant="elevated"
                      prepend-icon="mdi-check-all"
                      :loading="isProcessingGroup === group.mitarbeiterID"
                      @click.stop="payGroup(group)"
                  >
                    Alles Bezahlen
                  </v-btn>

                  <v-icon :icon="expandedGroupIds.includes(group.mitarbeiterID) ? 'mdi-chevron-up' : 'mdi-chevron-down'"></v-icon>
                </div>
              </div>
            </div>

            <v-expand-transition>
              <div v-if="expandedGroupIds.includes(group.mitarbeiterID)" class="bg-grey-lighten-5 pa-2 border-top">
                <div class="text-caption text-medium-emphasis mb-2 ml-2">Enthaltene Einzelabrechnungen:</div>

                <v-card
                    v-for="sub in group.items"
                    :key="sub.AbrechnungID"
                    variant="outlined"
                    class="mb-2 bg-white"
                >
                  <v-card-text class="py-2 d-flex justify-space-between align-center">
                    <div>
                      <div class="font-weight-bold d-flex align-center">
                        {{ sub.abteilung }}

                        <v-chip
                            v-if="hasHoliday(sub)"
                            size="x-small"
                            color="orange-darken-1"
                            variant="flat"
                            class="ml-2 font-weight-bold"
                            prepend-icon="mdi-party-popper"
                        >
                          Enthält Feiertage
                        </v-chip>
                      </div>
                      <div class="text-caption">{{ sub.zeitraum }} (ID: #{{ sub.AbrechnungID }})</div>
                    </div>

                    <div class="d-flex align-center gap-2">
                      <div class="text-right mr-2">
                        <div class="font-weight-medium">{{ formatCurrency(sub.gesamtBetrag) }}</div>
                        <div class="text-caption">{{ sub.stunden }} Std.</div>
                      </div>

                      <v-btn
                          icon="mdi-file-pdf-box"
                          size="small"
                          variant="text"
                          color="red-darken-1"
                          title="Einzelbeleg drucken"
                          @click="generateSinglePDF(sub)"
                      ></v-btn>

                      <v-btn
                          icon="mdi-check"
                          size="small"
                          variant="tonal"
                          color="success"
                          title="Nur diese Abrechnung bezahlen"
                          :loading="isProcessingId === sub.AbrechnungID"
                          @click="paySingle(sub)"
                      ></v-btn>
                    </div>
                  </v-card-text>
                </v-card>

                <div class="px-3 pb-2 pt-1" v-if="group.iban">
                  <span class="text-caption font-weight-bold">IBAN für Überweisung: </span>
                  <span class="text-caption font-monospace">{{ group.iban }}</span>
                </div>
                <div class="px-3 pb-2 pt-1 text-red text-caption" v-else>
                  <v-icon icon="mdi-alert-circle" size="x-small"></v-icon> Warnung: Keine IBAN hinterlegt.
                </div>

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

.group-card {
  background: white;
  border: 1px solid rgba(0,0,0,0.12);
  border-radius: 8px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.group-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.group-header { cursor: pointer; }
.gap-4 { gap: 16px; }
.gap-3 { gap: 12px; }
.gap-2 { gap: 8px; }
.border-top { border-top: 1px solid rgba(0,0,0,0.08); }
.font-monospace { font-family: monospace; }
</style>