<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router' // useRoute für URL-Parameter
import axios from 'axios'
import type { VForm } from 'vuetify/components'

type Department = { id: number, name: string }

const router = useRouter()
const route = useRoute() // Zugriff auf ?id=...

const form = ref<VForm | null>(null)
const isLoading = ref(false)

// Formular Felder
const date = ref('')
const startTime = ref('')
const endTime = ref('')
const course = ref('')
const selectedDepartment = ref<number | null>(null)

// EDIT MODE: Hier merken wir uns die ID, falls wir bearbeiten
const entryId = ref<string | null>(null)
const isEditMode = computed(() => !!entryId.value)

const departments = ref<Department[]>([])

// 1. Abteilungen laden
async function fetchUserDepartments() {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/meine-uel-abteilungen')
    departments.value = response.data
  } catch (error: any) {
    if (error.response?.status === 401) router.push({ name: 'Login' })
  }
}


// 2. Eintrag laden (nur bei Edit)
async function loadEntry(id: string) {
  isLoading.value = true
  try {
    const response = await axios.get(`http://127.0.0.1:8000/api/stundeneintrag/${id}`)
    const data = response.data

    entryId.value = id

    // Werte ins Formular setzen
    date.value = data.datum
    startTime.value = data.beginn?.substring(0, 5) || ''
    endTime.value = data.ende?.substring(0, 5) || ''
    course.value = data.kurs
    selectedDepartment.value = data.fk_abteilung

    // --- NEU: Snapshot erstellen ---
    // Wir speichern exakt die Werte, die wir gerade ins Formular geladen haben
    originalData.value = {
      datum: date.value,
      beginn: startTime.value,
      ende: endTime.value,
      kurs: course.value,
      fk_abteilung: selectedDepartment.value
    }

  } catch (error) {
    // ... Fehlerbehandlung ...
  } finally {
    isLoading.value = false
  }
}

// Speichert den Zustand direkt nach dem Laden
const originalData = ref<{
  datum: string,
  beginn: string,
  ende: string,
  kurs: string,
  fk_abteilung: number | null
} | null>(null)


function hasChanges(): boolean {
  // Wenn wir nicht im Edit-Mode sind (also neu erstellen), ist es immer eine Änderung
  if (!isEditMode.value || !originalData.value) return true

  // Vergleiche jedes Feld einzeln
  const isDateSame = date.value === originalData.value.datum
  const isStartSame = startTime.value === originalData.value.beginn
  const isEndSame = endTime.value === originalData.value.ende
  const isCourseSame = course.value === originalData.value.kurs
  const isDeptSame = selectedDepartment.value === originalData.value.fk_abteilung

  // Wenn ALLES gleich ist, haben wir KEINE Änderungen
  if (isDateSame && isStartSame && isEndSame && isCourseSame && isDeptSame) {
    return false
  }

  return true
}




onMounted(async () => {
  await fetchUserDepartments()

  // Prüfen: Ist eine ID in der URL? (?id=15)
  if (route.query.id) {
    await loadEntry(route.query.id as string)
  } else {
    // Nur bei NEU: Automatisch Abteilung wählen
    if (departments.value.length === 1) {
      selectedDepartment.value = departments.value[0].id
    }
  }
})

// Validierung (bleibt gleich)
const requiredRule = [(v: any) => !!v || 'Pflichtfeld']
const endTimeRules = [(v: string) => !startTime.value || v > startTime.value || 'Ende > Beginn']

// Submit
async function onSubmit(targetStatusId: number) {
  const { valid } = await form.value?.validate() || { valid: false }
  if (!valid) return

  // --- NEU: Prüfung auf Änderungen ---
  // Nur prüfen, wenn wir im Edit-Mode sind
  if (isEditMode.value && !hasChanges()) {
    alert("Es wurden keine Änderungen vorgenommen.")
    return // Abbruch, nichts ans Backend senden
  }

  isLoading.value = true

  // ... Rest dein bestehender Code (Payload erstellen, Axios Call etc.) ...
}

function goBack() {
  // Wenn Edit -> zurück zu Entwürfen, sonst Dashboard
  if (isEditMode.value) router.push({ name: 'Drafts' })
  else router.push({ name: 'Dashboard' })
}
</script>

<template>
  <div class="page">
    <div class="d-flex justify-start mb-4">
      <v-btn color="primary" variant="tonal" prepend-icon="mdi-arrow-left" @click="goBack">
        {{ isEditMode ? 'Abbrechen' : 'Zum Dashboard' }}
      </v-btn>
    </div>

    <v-card elevation="6" class="pa-4 auth-card">
      <v-progress-linear v-if="isLoading" indeterminate color="primary" absolute top></v-progress-linear>

      <v-card-title class="pa-0 pb-4">
        <h3 class="ma-0">{{ isEditMode ? 'Entwurf bearbeiten' : 'Stundenerfassung' }}</h3>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form ref="form">
          <v-select v-model="selectedDepartment" :items="departments" item-title="name" item-value="id" label="Abteilung" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4"></v-select>
          <v-text-field v-model="date" label="Datum" type="date" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4"></v-text-field>
          <div class="d-flex" style="gap: 16px;">
            <v-text-field v-model="startTime" label="Beginn" type="time" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4 flex-grow-1"></v-text-field>
            <v-text-field v-model="endTime" label="Ende" type="time" variant="outlined" density="comfortable" :rules="endTimeRules" class="mb-4 flex-grow-1"></v-text-field>
          </div>
          <v-combobox v-model="course" :items="courseOptions" label="Kurs" variant="outlined" density="comfortable" class="mb-4"></v-combobox>

          <div class="d-flex justify-center mt-6 flex-wrap" style="gap: 16px;">
            <v-btn color="blue-grey-lighten-4" variant="flat" style="min-width:140px" prepend-icon="mdi-content-save-outline" :loading="isLoading" @click="onSubmit(4)">
              {{ isEditMode ? 'Update Entwurf' : 'Entwurf' }}
            </v-btn>
            <v-btn color="primary" style="min-width:140px" prepend-icon="mdi-send" :loading="isLoading" @click="onSubmit(2)">
              Abschicken
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.page { padding: 24px; max-width: 640px; margin: 0 auto; }
</style>