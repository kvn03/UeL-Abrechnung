<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const items = ref<any[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const showDialog = ref(false)
const successMessage = ref<string | null>(null)
const formRef = ref()

// Form State (Nur noch Datenfelder, keine Datei, keine ID)
const form = reactive({
  name: '',
  nummer: '',
  gueltigVon: '',
  gueltigBis: ''
})

const API_URL = import.meta.env.VITE_API_URL + '/api/uebungsleiter/lizenzen'

// --- Datum Helper ---
function safeDate(val: string | null | undefined): string {
  if (!val) return '';
  const date = new Date(val);
  if (isNaN(date.getTime())) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// --- Status Helper ---
function getExpirationStatus(dateStr: string) {
  if (!dateStr) return { color: 'grey', bg: '', icon: 'mdi-help-circle', text: 'Unbekannt' };

  const today = new Date();
  today.setHours(0,0,0,0);

  const exp = new Date(dateStr);
  const diffTime = exp.getTime() - today.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays < 0) {
    return { color: 'error', bg: 'bg-red-lighten-5', icon: 'mdi-alert-circle', text: 'ABGELAUFEN' };
  } else if (diffDays <= 180) {
    return { color: 'warning', bg: 'bg-orange-lighten-5', icon: 'mdi-clock-alert-outline', text: 'Läuft bald ab' };
  }
  return { color: 'success', bg: '', icon: 'mdi-check-circle-outline', text: 'Gültig' };
}

const sortedItems = computed(() => {
  return [...items.value].sort((a, b) => {
    return new Date(a.gueltigBis).getTime() - new Date(b.gueltigBis).getTime();
  });
});

async function loadItems() {
  isLoading.value = true
  try {
    const res = await axios.get(API_URL)
    items.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

// Dialog öffnet IMMER für neuen Eintrag (kein Parameter mehr nötig)
function openDialog() {
  if (formRef.value) formRef.value.resetValidation();

  form.name = '';
  form.nummer = '';
  // Standard: Heute
  form.gueltigVon = safeDate(new Date().toISOString());
  form.gueltigBis = '';

  showDialog.value = true;
}

async function saveLicence() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSaving.value = true
  try {
    const payload = {
      ...form,
      nummer: String(form.nummer)
    }

    await axios.post(API_URL, payload)

    showDialog.value = false
    successMessage.value = 'Lizenz gemeldet.'
    await loadItems()
  } catch (e: any) {
    console.error(e)
    if (e.response?.data?.message) {
      alert("Fehler: " + e.response.data.message);
    }
  } finally {
    isSaving.value = false
  }
}

async function deleteItem(item: any) {
  if (!confirm(`Lizenz "${item.name}" wirklich löschen?`)) return
  try {
    await axios.delete(`${API_URL}/${item.ID}`)
    await loadItems()
  } catch(e) { console.error(e) }
}

function formatDate(d: string) {
  if(!d) return ''
  return new Date(d).toLocaleDateString('de-DE')
}

function goBack() { router.push({ name: 'Dashboard' }) }

onMounted(() => {
  loadItems()
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
      <v-card-title class="d-flex justify-space-between align-center mb-4">
        <div>
          <h3 class="ma-0">Meine Lizenzen</h3>
          <div class="text-caption text-medium-emphasis">Bitte melde hier deine aktuellen Lizenzen.</div>
        </div>
        <v-btn color="primary" size="small" prepend-icon="mdi-plus" @click="openDialog">
          Neu
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4" closable>{{ successMessage }}</v-alert>

        <div v-if="isLoading" class="text-center py-4">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else-if="sortedItems.length === 0" class="text-center text-medium-emphasis py-4">
          Keine Lizenzen hinterlegt.
        </div>

        <v-list v-else lines="two" class="bg-transparent">
          <template v-for="(item, index) in sortedItems" :key="item.ID">

            <v-list-item
                class="mb-2 rounded border"
                :class="getExpirationStatus(item.gueltigBis).bg"
            >
              <template v-slot:prepend>
                <v-avatar :color="getExpirationStatus(item.gueltigBis).color" variant="tonal">
                  <v-icon :icon="getExpirationStatus(item.gueltigBis).icon"></v-icon>
                </v-avatar>
              </template>

              <v-list-item-title class="font-weight-bold">
                {{ item.name }}
                <span v-if="item.nummer" class="text-caption text-medium-emphasis">({{ item.nummer }})</span>
              </v-list-item-title>

              <v-list-item-subtitle class="mt-1 d-flex align-center flex-wrap gap-2">
                <span>
                  Gültig: {{ formatDate(item.gueltigVon) }} bis
                  <span :class="'text-' + getExpirationStatus(item.gueltigBis).color" class="font-weight-bold">
                    {{ formatDate(item.gueltigBis) }}
                  </span>
                </span>

                <v-chip
                    size="x-small"
                    :color="getExpirationStatus(item.gueltigBis).color"
                    class="font-weight-bold"
                    variant="flat"
                >
                  {{ getExpirationStatus(item.gueltigBis).text }}
                </v-chip>
              </v-list-item-subtitle>

              <template v-slot:append>
                <div class="d-flex align-center">
                  <v-btn v-if="item.datei" icon="mdi-link" variant="text" size="small" :href="item.datei" target="_blank" color="blue" title="Lizenzdatei öffnen"></v-btn>

                  <v-btn icon="mdi-delete" variant="text" size="small" color="red" @click="deleteItem(item)"></v-btn>
                </div>
              </template>
            </v-list-item>
          </template>
        </v-list>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="600px">
      <v-card>
        <v-card-title>Neue Lizenz melden</v-card-title>
        <v-card-text>
          <v-form ref="formRef" @submit.prevent="saveLicence">
            <div class="d-flex flex-column gap-2 mt-2">
              <v-text-field v-model="form.name" label="Bezeichnung" variant="outlined" :rules="[v => !!v || 'Pflichtfeld']"></v-text-field>
              <v-text-field v-model="form.nummer" label="Nummer (opt.)" variant="outlined"></v-text-field>
              <div class="d-flex gap-2">
                <v-text-field v-model="form.gueltigVon" label="Gültig Von" type="date" variant="outlined" :rules="[v => !!v || 'Pflichtfeld']"></v-text-field>
                <v-text-field v-model="form.gueltigBis" label="Gültig Bis" type="date" variant="outlined" :rules="[v => !!v || 'Pflichtfeld']"></v-text-field>
              </div>

              <v-alert type="info" variant="tonal" density="compact" class="mt-2 text-caption">
                Bitte reiche den Scan deiner Lizenz bei der Abteilungsleitung oder der Geschäftsstelle nach. Diese wird dann hier hinterlegt.
              </v-alert>

            </div>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDialog = false">Abbrechen</v-btn>
          <v-btn color="primary" :loading="isSaving" @click="saveLicence">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 800px; margin: 0 auto; }
.gap-2 { gap: 8px; }
.bg-red-lighten-5 { background-color: #FFEBEE !important; }
.bg-orange-lighten-5 { background-color: #FFF3E0 !important; }
</style>