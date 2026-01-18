<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const items = ref<any[]>([])
const search = ref('')
const isLoading = ref(false)
const isSaving = ref(false)
const showDialog = ref(false)
const formRef = ref()

// API URL
const API_URL = import.meta.env.VITE_API_URL + '/api/geschaeftsstelle/lizenzen'

// Form State
const editedItem = reactive({
  ID: null,
  vorname: '',
  nachname: '',
  name: '', // Lizenzname
  nummer: '',
  gueltigVon: '',
  gueltigBis: '',
  datei: ''
})

// Tabellen-Header
const headers = [
  { title: 'Name', key: 'fullname', sortable: true },
  { title: 'Lizenz', key: 'name', sortable: true },
  { title: 'Gültigkeit', key: 'gueltigBis', sortable: true },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Datei', key: 'datei', sortable: false },
  { title: 'Aktionen', key: 'actions', sortable: false, align: 'end' },
]

// --- Helper Funktionen (Datum & Status) ---
function safeDate(val: string | null | undefined): string {
  if (!val) return '';
  const date = new Date(val);
  if (isNaN(date.getTime())) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function goBack() {
  router.push({ name: 'Dashboard' })
}
function getStatus(dateStr: string) {
  if (!dateStr) return { color: 'grey', text: '?' };
  const today = new Date();
  today.setHours(0,0,0,0);
  const exp = new Date(dateStr);
  const diffDays = Math.ceil((exp.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

  if (diffDays < 0) return { color: 'error', text: 'Abgelaufen' };
  if (diffDays <= 180) return { color: 'warning', text: 'Läuft ab' };
  return { color: 'success', text: 'Gültig' };
}

function formatDate(d: string) {
  if(!d) return '-'
  return new Date(d).toLocaleDateString('de-DE')
}

// --- API ---
async function loadData() {
  isLoading.value = true
  try {
    const res = await axios.get(API_URL)
    // Wir bauen ein "fullname" Feld für die Suche/Anzeige
    items.value = res.data.map((item: any) => ({
      ...item,
      fullname: `${item.nachname}, ${item.vorname}`
    }))
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

function editItem(item: any) {
  editedItem.ID = item.ID
  editedItem.vorname = item.vorname
  editedItem.nachname = item.nachname
  editedItem.name = item.name

  // FIX: Explizit in String wandeln
  editedItem.nummer = item.nummer ? String(item.nummer) : ''

  editedItem.gueltigVon = safeDate(item.gueltigVon)
  editedItem.gueltigBis = safeDate(item.gueltigBis)
  editedItem.datei = item.datei

  showDialog.value = true
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSaving.value = true
  try {
    // PUT Request an Backend
    await axios.put(`${API_URL}/${editedItem.ID}`, {
      name: editedItem.name,

      // FIX: Hier sicherstellen, dass es ein String ist
      nummer: String(editedItem.nummer),

      gueltigVon: editedItem.gueltigVon,
      gueltigBis: editedItem.gueltigBis,
      datei: editedItem.datei
    })

    showDialog.value = false
    await loadData()
  } catch (e: any) { // Typ 'any' hilft hier für Zugriff auf e.response
    console.error(e)
    // Optional: Genauere Fehlermeldung anzeigen
    const msg = e.response?.data?.message || 'Fehler beim Speichern'
    alert(msg)
  } finally {
    isSaving.value = false
  }
}

async function deleteItem(item: any) {
  if (!confirm(`Lizenz von ${item.vorname} ${item.nachname} wirklich löschen?`)) return
  try {
    await axios.delete(`${API_URL}/${item.ID}`)
    await loadData()
  } catch (e) { console.error(e) }
}

onMounted(() => loadData())
</script>

<template>
  <div class="page pa-4">
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

    <div class="mb-4">
      <h2>Lizenzverwaltung</h2>
    </div>

    <v-card>
      <v-data-table
          :headers="headers"
          :items="items"
          :search="search"
          :loading="isLoading"
          class="elevation-1"
      >
        <template v-slot:top>
          <v-toolbar flat density="compact">
            <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                label="Suchen (Name, Lizenz...)"
                single-line
                hide-details
                variant="plain"
                class="px-4"
            ></v-text-field>
            <v-spacer></v-spacer>
            <v-btn icon="mdi-refresh" @click="loadData"></v-btn>
          </v-toolbar>
        </template>

        <template v-slot:item.fullname="{ item }">
          <div class="font-weight-bold">{{ item.fullname }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
        </template>

        <template v-slot:item.gueltigBis="{ item }">
          {{ formatDate(item.gueltigVon) }} - {{ formatDate(item.gueltigBis) }}
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip :color="getStatus(item.gueltigBis).color" size="small" label>
            {{ getStatus(item.gueltigBis).text }}
          </v-chip>
        </template>

        <template v-slot:item.datei="{ item }">
          <v-icon v-if="item.datei" color="blue" icon="mdi-link-check" title="Link hinterlegt"></v-icon>
          <v-icon v-else color="grey-lighten-2" icon="mdi-link-off" title="Kein Link"></v-icon>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-btn
              size="small"
              color="primary"
              variant="flat"
              class="mr-2"
              @click="editItem(item)"
          >
            <v-icon icon="mdi-pencil"></v-icon> Bearbeiten / Link
          </v-btn>
          <v-btn
              size="small"
              color="error"
              variant="text"
              icon="mdi-delete"
              @click="deleteItem(item)"
          ></v-btn>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="showDialog" max-width="600px">
      <v-card>
        <v-toolbar color="primary" density="compact">
          <v-toolbar-title>Lizenz verwalten</v-toolbar-title>
          <v-spacer></v-spacer>
          <v-btn icon="mdi-close" @click="showDialog = false"></v-btn>
        </v-toolbar>

        <v-card-text class="pt-4">
          <div class="text-subtitle-1 mb-2 font-weight-bold">
            {{ editedItem.vorname }} {{ editedItem.nachname }}
          </div>

          <v-form ref="formRef" @submit.prevent="save">
            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field v-model="editedItem.name" label="Lizenzbezeichnung" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model="editedItem.nummer" label="Nummer" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="6">
                <v-text-field v-model="editedItem.gueltigVon" label="Von" type="date" variant="outlined" density="compact"></v-text-field>
              </v-col>
              <v-col cols="6">
                <v-text-field v-model="editedItem.gueltigBis" label="Bis" type="date" variant="outlined" density="compact"></v-text-field>
              </v-col>
            </v-row>

            <v-divider class="my-4"></v-divider>

            <div class="bg-blue-grey-lighten-5 pa-3 rounded border">
              <div class="d-flex align-center mb-2">
                <v-icon icon="mdi-cloud-upload" color="primary" class="mr-2"></v-icon>
                <span class="font-weight-bold">Datei-Link hinterlegen (Geschäftsstelle)</span>
              </div>
              <v-text-field
                  v-model="editedItem.datei"
                  label="Link zu OneDrive / Dropbox / PDF"
                  placeholder="https://..."
                  variant="outlined"
                  bg-color="white"
                  prepend-inner-icon="mdi-link"
                  :rules="[v => !v || v.startsWith('http') || 'Muss mit http beginnen']"
              ></v-text-field>
              <div v-if="editedItem.datei" class="text-caption mt-1">
                <a :href="editedItem.datei" target="_blank">Link testen <v-icon size="x-small" icon="mdi-open-in-new"></v-icon></a>
              </div>
            </div>

          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDialog = false">Abbrechen</v-btn>
          <v-btn color="primary" @click="save" :loading="isSaving">Speichern</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>