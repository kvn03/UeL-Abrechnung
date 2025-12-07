<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const drafts = ref<any[]>([])
const isLoading = ref(true)


function goBack() {
  router.push({ name: 'Dashboard' })
}

// Hilfsfunktion: Datum formatieren (YYYY-MM-DD -> DD.MM.YYYY)
function formatDate(dateStr: string) {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}.${m}.${y}`
}

// Hilfsfunktion: Zeit formatieren (HH:MM:SS -> HH:MM)
function formatTime(timeStr: string) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

async function fetchDrafts() {
  isLoading.value = true
  const token = localStorage.getItem('token')

  try {
    const response = await axios.get('http://127.0.0.1:8000/api/entwuerfe')
    drafts.value = response.data
  } catch (error) {
    console.error('Fehler beim Laden der Entwürfe', error)
  } finally {
    isLoading.value = false
  }
}

// Aktion: Löschen (Dummy Funktion)
// In Drafts.vue im <script setup>

async function deleteDraft(id: number) {
  if (!confirm("Möchtest du diesen Entwurf wirklich unwiderruflich löschen?")) {
    return;
  }

  // Ladezustand aktivieren (optional, falls du einen globalen Loader hast)
  // isLoading.value = true;

  try {
    // 1. API Aufruf (DELETE)
    // Wir hängen die ID hinten an die URL an
    await axios.delete(`http://127.0.0.1:8000/api/stundeneintrag/${id}`);

    // 2. Aus der Liste entfernen (UI Update ohne Neuladen)
    drafts.value = drafts.value.filter(draft => draft.EintragID !== id);

    // Optional: Kurzes Feedback (Snackbar oder Alert)
    // alert("Gelöscht!");

  } catch (error: any) {
    console.error("Fehler beim Löschen:", error);
    alert("Fehler: " + (error.response?.data?.message || "Konnte nicht gelöscht werden."));
  } finally {
    // isLoading.value = false;
  }
}

// Aktion: Bearbeiten (Dummy Funktion)
function editDraft(draft: any) {
  // Wir nutzen query parameter: /timesheet?id=123
  // Stelle sicher, dass deine Route im Router 'Timesheet' heißt!
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
      <v-card-title class="pa-0 mb-4 d-flex justify-space-between align-center">
        <h3 class="ma-0">Stunden-Entwürfe</h3>
        <v-chip color="blue-grey" size="small" variant="flat">
          {{ drafts.length }} Entwürfe
        </v-chip>
      </v-card-title>

      <v-card-text class="pa-0">

        <div v-if="isLoading" class="d-flex justify-center pa-4">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else-if="drafts.length === 0" class="placeholder">
          Keine Entwürfe vorhanden.
        </div>

        <div v-else class="d-flex flex-column" style="gap: 12px;">
          <v-card
              v-for="draft in drafts"
              :key="draft.EintragID"
              variant="outlined"
              class="draft-item pa-3"
          >
            <div class="d-flex justify-space-between align-start">
              <div>
                <div class="text-subtitle-1 font-weight-bold text-primary">
                  {{ formatDate(draft.datum) }}
                </div>
                <div class="text-body-2 mb-1">
                  <v-icon size="small" icon="mdi-clock-outline" class="mr-1"></v-icon>
                  {{ formatTime(draft.beginn) }} - {{ formatTime(draft.ende) }} Uhr
                  <span class="text-medium-emphasis">({{ draft.dauer }} Std.)</span>
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

              <div class="d-flex flex-column" style="gap: 8px;">
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
  transition: background-color 0.2s;
}
.draft-item:hover {
  background-color: #fafafa;
}
</style>