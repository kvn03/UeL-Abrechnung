<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const drafts = ref<any[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false) // Neuer State für Ladeanimation beim Einreichen

// Hier speichern wir die IDs der ausgewählten Entwürfe
const selectedIds = ref<number[]>([])

function goBack() {
  router.push({ name: 'Dashboard' })
}

// Hilfsfunktion: Datum formatieren
function formatDate(dateStr: string) {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}.${m}.${y}`
}

// Hilfsfunktion: Zeit formatieren
function formatTime(timeStr: string) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

// Berechnete Eigenschaft: Summe der Stunden der ausgewählten Einträge
const selectedHoursSum = computed(() => {
  const selected = drafts.value.filter(d => selectedIds.value.includes(d.EintragID))
  // Annahme: 'dauer' kommt als double/float vom Backend.
  // Wir summieren auf und runden auf 2 Nachkommastellen.
  const sum = selected.reduce((acc, curr) => acc + Number(curr.dauer), 0)
  return sum.toFixed(2)
})

// Berechnete Eigenschaft: Alles ausgewählt?
const allSelected = computed(() => {
  return drafts.value.length > 0 && selectedIds.value.length === drafts.value.length
})

// Funktion: Alles umschalten
function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = drafts.value.map(d => d.EintragID)
  }
}

async function fetchDrafts() {
  isLoading.value = true
  // Reset Selection beim Neuladen
  selectedIds.value = []

  try {
    const response = await axios.get('http://127.0.0.1:8000/api/entwuerfe')
    drafts.value = response.data
  } catch (error) {
    console.error('Fehler beim Laden der Entwürfe', error)
  } finally {
    isLoading.value = false
  }
}

// Aktion: Ausgewählte einreichen (Abrechnung erstellen)
async function submitSelection() {
  if (selectedIds.value.length === 0) return

  const confirmMsg = `Möchtest du ${selectedIds.value.length} Einträge mit insgesamt ${selectedHoursSum.value} Stunden jetzt abrechnen?`
  if (!confirm(confirmMsg)) return

  isSubmitting.value = true

  try {
    // Hier rufen wir den Controller auf, den wir vorhin erstellt haben
    await axios.post(import.meta.env.VITE_API_URL + '/api/abrechnung/erstellen', {
      stundeneintrag_ids: selectedIds.value
    })

    // Erfolgsmeldung (einfach gehalten)
    alert('Abrechnung erfolgreich erstellt!')

    // Liste neu laden, damit die eingereichten Einträge verschwinden
    await fetchDrafts()

  } catch (error: any) {
    console.error("Fehler beim Einreichen:", error)
    alert("Fehler: " + (error.response?.data?.message || "Konnte nicht eingereicht werden."))
  } finally {
    isSubmitting.value = false
  }
}

async function deleteDraft(id: number) {
  if (!confirm("Möchtest du diesen Entwurf wirklich unwiderruflich löschen?")) return;

  try {
    await axios.delete(`http://127.0.0.1:8000/api/stundeneintrag/${id}`);

    // UI Update
    drafts.value = drafts.value.filter(draft => draft.EintragID !== id);
    // Falls die gelöschte ID ausgewählt war, entfernen wir sie aus der Selection
    selectedIds.value = selectedIds.value.filter(selectedId => selectedId !== id)

  } catch (error: any) {
    console.error("Fehler beim Löschen:", error);
    alert("Fehler: " + (error.response?.data?.message || "Konnte nicht gelöscht werden."));
  }
}

function editDraft(draft: any) {
  router.push({
    name: 'Timesheet',
    query: { id: draft.EintragID }
  })
}

onMounted(() => {
  fetchDrafts()
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

      <v-card-title class="pa-0 mb-4 d-flex justify-space-between align-center flex-wrap">
        <div class="d-flex align-center">
          <h3 class="ma-0 mr-4">Stunden-Entwürfe</h3>
          <v-chip color="blue-grey" size="small" variant="flat">
            {{ drafts.length }} Gesamt
          </v-chip>
        </div>

        <div v-if="drafts.length > 0">
          <v-btn
              color="success"
              :disabled="selectedIds.length === 0 || isSubmitting"
              :loading="isSubmitting"
              prepend-icon="mdi-check-all"
              @click="submitSelection"
          >
            {{ selectedIds.length > 0 ? `(${selectedIds.length}) Einreichen` : 'Einreichen' }}
          </v-btn>
        </div>
      </v-card-title>

      <v-expand-transition>
        <div v-if="selectedIds.length > 0" class="mb-3 pa-2 bg-blue-grey-lighten-5 rounded border d-flex justify-space-between align-center">
          <span class="text-body-2 font-weight-medium text-blue-grey-darken-2">
            <v-icon icon="mdi-calculator" size="small" class="mr-1"></v-icon>
            Ausgewählt: {{ selectedIds.length }} Einträge
          </span>
          <span class="text-subtitle-2 font-weight-bold text-primary">
            Summe: {{ selectedHoursSum }} Std.
          </span>
        </div>
      </v-expand-transition>

      <v-card-text class="pa-0">

        <div v-if="isLoading" class="d-flex justify-center pa-4">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else-if="drafts.length === 0" class="placeholder">
          Keine offenen Entwürfe vorhanden.
        </div>

        <div v-else class="d-flex flex-column" style="gap: 12px;">

          <div class="d-flex align-center px-3 py-1">
            <v-checkbox-btn
                :model-value="allSelected"
                @update:model-value="toggleSelectAll"
                color="primary"
                class="mr-2"
            ></v-checkbox-btn>
            <span class="text-caption text-medium-emphasis cursor-pointer" @click="toggleSelectAll">
               {{ allSelected ? 'Auswahl aufheben' : 'Alle auswählen' }}
             </span>
          </div>

          <v-card
              v-for="draft in drafts"
              :key="draft.EintragID"
              variant="outlined"
              class="draft-item pa-3"
              :class="{ 'selected-card': selectedIds.includes(draft.EintragID) }"
          >
            <div class="d-flex align-start">

              <div class="mr-3 mt-1">
                <v-checkbox-btn
                    v-model="selectedIds"
                    :value="draft.EintragID"
                    color="primary"
                    density="compact"
                ></v-checkbox-btn>
              </div>

              <div class="flex-grow-1">
                <div class="d-flex justify-space-between align-start">
                  <div>
                    <div class="text-subtitle-1 font-weight-bold text-primary">
                      {{ formatDate(draft.datum) }}
                    </div>
                    <div class="text-body-2 mb-1">
                      <v-icon size="small" icon="mdi-clock-outline" class="mr-1"></v-icon>
                      {{ formatTime(draft.beginn) }} - {{ formatTime(draft.ende) }} Uhr
                      <span class="font-weight-bold ml-1">({{ draft.dauer }} Std.)</span>
                    </div>
                    <div class="text-body-2">
                      <v-icon size="small" icon="mdi-domain" class="mr-1"></v-icon>
                      {{ draft.abteilung?.name || 'Keine Abteilung' }}
                    </div>
                    <div v-if="draft.kurs" class="text-body-2 mt-1">
                      <v-chip size="x-small" label class="mr-1">Kurs</v-chip>
                      {{ draft.kurs }}
                    </div>
                  </div>

                  <div class="d-flex flex-column" style="gap: 4px;">
                    <v-btn
                        icon="mdi-pencil"
                        size="small"
                        variant="text"
                        color="blue"
                        @click="editDraft(draft)"
                        title="Bearbeiten"
                    ></v-btn>
                    <v-btn
                        icon="mdi-delete"
                        size="small"
                        variant="text"
                        color="red"
                        @click="deleteDraft(draft.EintragID)"
                        title="Löschen"
                    ></v-btn>
                  </div>
                </div>
              </div>
            </div>
          </v-card>
        </div>

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

.placeholder {
  min-height: 150px;
  display: flex;
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

.draft-item {
  border-color: rgba(0,0,0,0.12);
  transition: all 0.2s;
  background-color: white;
}

/* Hervorhebung wenn ausgewählt */
.selected-card {
  border-color: #1976D2 !important; /* Primary Color */
  background-color: #F5F9FF !important; /* Sehr helles Blau */
  border-width: 1.5px;
}

.draft-item:hover {
  background-color: #fafafa;
}

.cursor-pointer {
  cursor: pointer;
}
</style>