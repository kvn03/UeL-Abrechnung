<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

type Department = { id: number, name: string }

const router = useRouter()
const route = useRoute()

const form = ref<VForm | null>(null)
const isLoading = ref(false)

// Formular Felder
const date = ref('')
const startTime = ref('')
const endTime = ref('')
const course = ref('')
const selectedDepartment = ref<number | null>(null)

// EDIT MODE
const entryId = ref<string | null>(null)
const isEditMode = computed(() => !!entryId.value)

const departments = ref<Department[]>([])

// Snapshot für Änderungen
const originalData = ref<{
  datum: string,
  beginn: string,
  ende: string,
  kurs: string,
  fk_abteilung: number | null
} | null>(null)

// 1. Abteilungen laden
async function fetchUserDepartments() {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/meine-uel-abteilungen')
    departments.value = response.data
  } catch (error: any) {
    if (error.response?.status === 401) router.push({ name: 'Login' })
  }
}

// 2. Eintrag laden
async function loadEntry(id: string) {
  isLoading.value = true
  try {
    const response = await axios.get(`http://127.0.0.1:8000/api/stundeneintrag/${id}`)
    const data = response.data

    entryId.value = id

    date.value = data.datum
    startTime.value = data.beginn?.substring(0, 5) || ''
    endTime.value = data.ende?.substring(0, 5) || ''
    course.value = data.kurs
    selectedDepartment.value = data.fk_abteilung

    originalData.value = {
      datum: date.value,
      beginn: startTime.value,
      ende: endTime.value,
      kurs: course.value,
      fk_abteilung: selectedDepartment.value
    }

  } catch (error) {
    alert("Fehler beim Laden.")
    goBack()
  } finally {
    isLoading.value = false
  }
}

function hasChanges(): boolean {
  if (!isEditMode.value || !originalData.value) return true

  const isDateSame = date.value === originalData.value.datum
  const isStartSame = startTime.value === originalData.value.beginn
  const isEndSame = endTime.value === originalData.value.ende
  const isCourseSame = course.value === originalData.value.kurs
  const isDeptSame = selectedDepartment.value === originalData.value.fk_abteilung

  return !(isDateSame && isStartSame && isEndSame && isCourseSame && isDeptSame)
}

onMounted(async () => {
  await fetchUserDepartments()
  if (route.query.id) {
    await loadEntry(route.query.id as string)
  } else {
    if (departments.value.length === 1) {
      selectedDepartment.value = departments.value[0].id
    }
  }
})

const requiredRule = [(v: any) => !!v || 'Pflichtfeld']
const endTimeRules = [(v: string) => !startTime.value || v > startTime.value || 'Ende > Beginn']

async function deleteEntry() {
  if (!confirm("Möchtest du diesen Eintrag wirklich löschen?")) return

  isLoading.value = true
  try {
    await axios.delete(`http://127.0.0.1:8000/api/stundeneintrag/${entryId.value}`)
    router.push({ name: 'Drafts' })
  } catch (error: any) {
    alert("Fehler beim Löschen: " + (error.response?.data?.message || 'Unbekannt'))
  } finally {
    isLoading.value = false
  }
}

// --- SUBMIT (Vereinfacht: Immer Status 10) ---
async function onSubmit() {
  const { valid } = await form.value?.validate() || { valid: false }
  if (!valid) return

  if (isEditMode.value && !hasChanges()) {
    alert("Es wurden keine Änderungen vorgenommen.")
    return
  }

  isLoading.value = true

  const payload = {
    datum: date.value,
    beginn: startTime.value,
    ende: endTime.value,
    kurs: course.value,
    fk_abteilung: selectedDepartment.value,
    status_id: 10
  }

  try {
    if (isEditMode.value) {
      await axios.put(`http://127.0.0.1:8000/api/stundeneintrag/${entryId.value}`, payload)
      alert("Eintrag aktualisiert!")
    } else {
      await axios.post('http://127.0.0.1:8000/api/stundeneintrag', payload)
      alert("Eintrag erfolgreich erstellt!")
    }
    goBack()

  } catch (error: any) {
    console.error(error)
    alert('Fehler: ' + (error.response?.data?.message || 'Serverfehler'))
  } finally {
    isLoading.value = false
  }
}

function goBack() {
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

      <v-card-title class="pa-0 pb-4 d-flex justify-space-between align-center">
        <h3 class="ma-0">{{ isEditMode ? 'Eintrag bearbeiten' : 'Stundenerfassung' }}</h3>

        <v-btn
            v-if="isEditMode"
            color="error"
            variant="text"
            icon="mdi-delete"
            @click="deleteEntry"
            title="Eintrag löschen"
        ></v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form ref="form">
          <v-select v-model="selectedDepartment" :items="departments" item-title="name" item-value="id" label="Abteilung" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4"></v-select>
          <v-text-field v-model="date" label="Datum" type="date" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4"></v-text-field>
          <div class="d-flex" style="gap: 16px;">
            <v-text-field v-model="startTime" label="Beginn" type="time" variant="outlined" density="comfortable" :rules="requiredRule" class="mb-4 flex-grow-1"></v-text-field>
            <v-text-field v-model="endTime" label="Ende" type="time" variant="outlined" density="comfortable" :rules="endTimeRules" class="mb-4 flex-grow-1"></v-text-field>
          </div>

          <v-text-field
              v-model="course"
              label="Kurs"
              variant="outlined"
              density="comfortable"
              :rules="requiredRule"
              class="mb-4"
          ></v-text-field>

          <div class="d-flex justify-center mt-6">
            <v-btn
                color="primary"
                class="submit-btn"
                style="min-width:160px"
                prepend-icon="mdi-content-save-check"
                :loading="isLoading"
                @click="onSubmit()"
            >
              {{ isEditMode ? 'Speichern' : 'Erstellen' }}
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