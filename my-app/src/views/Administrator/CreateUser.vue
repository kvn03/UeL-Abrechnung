<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

type Department = {
  id: number
  name: string
}

const form = ref<VForm | null>(null)
const isLoading = ref(false)

const email = ref('')
const firstName = ref('')
const lastName = ref('')

const isOffice = ref(false)
const isDepartmentHead = ref(false)
const isTrainer = ref(false)

const selectedHeadDepartment = ref<number | null>(null)
const selectedTrainerDepartments = ref<number[]>([])

const departments = ref<Department[]>([])
const router = useRouter()

async function fetchDepartments() {
  try {
    const response = await axios.get(import.meta.env.VITE_API_URL + '/api/abteilungen')
    departments.value = response.data
  } catch (error) {
    console.error('Konnte Abteilungen nicht laden. Ist das Backend gestartet?', error)
  }
}

onMounted(() => {
  fetchDepartments()
})

const emailRules = [
  (v: string) => !!v?.trim() || 'E-Mail ist erforderlich',
  (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'E-Mail muss gültig sein',
]

const requiredRules = [
  (v: string) => !!v?.trim() || 'Dieses Feld ist erforderlich',
]

watch(isDepartmentHead, (isActive) => {
  if (!isActive) selectedHeadDepartment.value = null
})

watch(isTrainer, (isActive) => {
  if (!isActive) selectedTrainerDepartments.value = []
})

async function onSubmit() {
  const { valid } = await form.value?.validate() || { valid: false }
  if (!valid) return

  isLoading.value = true

  const payload = {
    email: email.value,
    vorname: firstName.value,
    name: lastName.value,
    isGeschaeftsstelle: isOffice.value,
    roles: {
      departmentHead: isDepartmentHead.value && selectedHeadDepartment.value !== null
          ? [selectedHeadDepartment.value]
          : [],
      trainer: isTrainer.value ? selectedTrainerDepartments.value : [],
    }
  }

  try {
    const response = await axios.post(import.meta.env.VITE_API_URL + '/api/create-user', payload)

    console.log('Erfolg:', response.data)
    alert(`Benutzer ${firstName.value} erfolgreich angelegt!`)

    form.value?.reset()
    isOffice.value = false
    isDepartmentHead.value = false
    isTrainer.value = false
    selectedHeadDepartment.value = null
    selectedTrainerDepartments.value = []
  } catch (error: any) {
    console.error('API Error:', error)
    let errorMsg = error.response?.data?.message || 'Serverfehler beim Anlegen.'
    if (error.response?.data?.errors) {
      errorMsg += '\n' + JSON.stringify(error.response.data.errors, null, 2)
    }
    alert(errorMsg)
  } finally {
    isLoading.value = false

  }
}
function goBack() {
  router.push({ name: 'Dashboard' })
}
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
    <v-card elevation="6" class="pa-4 auth-card">

      <v-progress-linear
          v-if="isLoading"
          indeterminate
          color="primary"
          absolute
          top
      ></v-progress-linear>

      <v-card-title class="pa-0 pb-4">
        <div>
          <h3 class="ma-0">Benutzererstellung</h3>
          <div class="caption">Bitte Daten des Benutzers eintragen:</div>
        </div>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form ref="form" @submit.prevent="onSubmit">

          <v-text-field
              v-model="email"
              label="E-Mail"
              type="email"
              :rules="emailRules"
              density="comfortable"
              autocomplete="email"
              placeholder="E-Mail"
              required
              class="mb-4"
          />

          <v-text-field
              v-model="firstName"
              label="Vorname"
              type="text"
              :rules="requiredRules"
              density="comfortable"
              placeholder="Vorname"
              required
              class="mb-4"
          />

          <v-text-field
              v-model="lastName"
              label="Nachname"
              type="text"
              :rules="requiredRules"
              density="comfortable"
              placeholder="Nachname"
              required
              class="mb-4"
          />

          <div class="role-group" :class="{ 'role-active': isOffice }">
            <v-checkbox
                v-model="isOffice"
                label="Geschäftsstelle"
                color="primary"
                true-icon="mdi-checkbox-marked"
                false-icon="mdi-checkbox-blank-outline"
                base-color="grey-darken-1"
                hide-details
                density="compact"
            ></v-checkbox>
          </div>

          <div class="role-group mt-2" :class="{ 'role-active': isDepartmentHead }">
            <v-checkbox
                v-model="isDepartmentHead"
                label="Abteilungsleitung"
                color="primary"
                true-icon="mdi-checkbox-marked"
                false-icon="mdi-checkbox-blank-outline"
                base-color="grey-darken-1"
                hide-details
                density="compact"
            ></v-checkbox>

            <v-expand-transition>
              <div v-if="isDepartmentHead" class="pl-8 pt-2">
                <v-select
                    v-model="selectedHeadDepartment"
                    :items="departments"
                    item-title="name"
                    item-value="id"
                    label="Abteilung wählen"
                    variant="outlined"
                    density="compact"
                    placeholder="Bitte wählen..."
                    :rules="[v => !!v || 'Bitte eine Abteilung wählen']"
                    no-data-text="Keine Abteilungen geladen"
                ></v-select>
              </div>
            </v-expand-transition>
          </div>

          <div class="role-group mt-2" :class="{ 'role-active': isTrainer }">
            <v-checkbox
                v-model="isTrainer"
                label="Übungsleiter"
                color="primary"
                true-icon="mdi-checkbox-marked"
                false-icon="mdi-checkbox-blank-outline"
                base-color="grey-darken-1"
                hide-details
                density="compact"
            ></v-checkbox>

            <v-expand-transition>
              <div v-if="isTrainer" class="pl-8 pt-2">
                <v-select
                    v-model="selectedTrainerDepartments"
                    :items="departments"
                    item-title="name"
                    item-value="id"
                    label="Abteilungen wählen"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="compact"
                    placeholder="Bitte wählen..."
                    :rules="[v => v.length > 0 || 'Bitte mindestens eine Abteilung wählen']"
                    no-data-text="Keine Abteilungen geladen"
                ></v-select>
              </div>
            </v-expand-transition>
          </div>

          <div class="d-flex justify-center mt-6">
            <v-btn
                color="primary"
                class="mx-auto submit-btn"
                style="min-width:160px"
                type="submit"
                :loading="isLoading"
                :disabled="isLoading"
            >
              Benutzer erstellen
            </v-btn>
          </div>

        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

/* Vue */
<style scoped>

.page {
  padding: 24px;
  /* max-width und margin entfernt */
}

.d-flex.justify-start.mb-4 {
  justify-content: flex-start;
  margin-bottom: 16px;
  max-width: 420px; /* Hinzugefügt */
  margin-left: auto;  /* Hinzugefügt */
  margin-right: auto; /* Hinzugefügt */
}

.auth-card {
  width: 100%;
  max-width: 420px;
  border-radius: 12px;
  margin: 0 auto;
}

.submit-btn {
  font-weight: 600;
}

.role-group {
  border-radius: 8px;
  padding: 4px;
  transition: background-color 0.2s ease;
}

.role-active {
  background-color: rgba(25, 118, 210, 0.08);
}

.role-active :deep(.v-label) {
  font-weight: 600;
  color: #1565C0;
  opacity: 1;
}

</style>



