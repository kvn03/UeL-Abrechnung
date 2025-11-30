<!-- language: vue -->
<script setup lang="ts">
import { ref, watch, type Ref } from 'vue'

const form = ref<any>(null)

const email = ref('')
const firstName = ref('')
const lastName = ref('')

const isOffice = ref(false)
const isDepartmentHead = ref(false)
const isTrainer = ref(false)

const departments = [
  'Abteilung 1',
  'Abteilung 2',
  'Abteilung 3',
  'Abteilung 4',
  'Abteilung 5',
]

const officeDepartments = ref<string[]>([])
const departmentHeadDepartments = ref<string[]>([])
const trainerDepartments = ref<string[]>([])

const emailRules = [
  (v: string) => !!v?.trim() || 'Email ist erforderlich',
  (v: string) =>
      /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v?.trim()) ||
      'Gib eine gültige E-Mail ein',
]

const requiredRules = [
  (v: string) => !!v?.trim() || 'Dieses Feld ist erforderlich',
]

watch(isOffice, (val) => {
  if (!val) officeDepartments.value = []
})
watch(isDepartmentHead, (val) => {
  if (!val) departmentHeadDepartments.value = []
})
watch(isTrainer, (val) => {
  if (!val) trainerDepartments.value = []
})

function toggleFromList(listRef: Ref<string[]>, dept: string) {
  const list = listRef.value
  if (list.includes(dept)) {
    listRef.value = list.filter((d) => d !== dept)
  } else {
    listRef.value = [...list, dept]
  }
}

const toggleDepartmentHeadDepartment = (dept: string) =>
    toggleFromList(departmentHeadDepartments, dept)
const toggleTrainerDepartment = (dept: string) =>
    toggleFromList(trainerDepartments, dept)

async function onSubmit() {
  const result = await Promise.resolve(form.value?.validate?.())
  const isValid = typeof result === 'boolean' ? result : !!result?.valid
  if (!isValid) return
}
</script>

<template>
  <div class="auth-page">
    <v-card elevation="6" class="pa-4 auth-card">
      <v-form ref="form" @submit.prevent="onSubmit">
        <v-card-title class="pa-0 pb-4">
          <div>
            <h3 class="ma-0">Benutzererstellung</h3>
            <div class="caption">Bitte Daten des Benutzers eintragen:</div>
          </div>
        </v-card-title>

        <v-card-text class="pa-0">
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

          <!-- Geschäftsstelle: nur Checkbox, kein Dropdown -->
          <div class="role-row">
            <label class="role-checkbox">
              <input type="checkbox" v-model="isOffice" />
              <span>Geschäftsstelle</span>
            </label>
          </div>

          <!-- Abteilungsleitung: Checkbox + Dropdown -->
          <div class="role-row">
            <label class="role-checkbox">
              <input type="checkbox" v-model="isDepartmentHead" />
              <span>Abteilungsleitung</span>
            </label>

            <div class="role-dropdown-wrapper">
              <div v-if="isDepartmentHead" class="dropdown">
                <div class="dropdown-header">Abteilung</div>
                <div class="dropdown-list">
                  <div
                      v-for="dept in departments"
                      :key="dept"
                      class="dropdown-item"
                      :class="{
                      'dropdown-item--selected':
                        departmentHeadDepartments.includes(dept),
                    }"
                      @click="toggleDepartmentHeadDepartment(dept)"
                  >
                    {{ dept }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Übungsleiter: Checkbox + Dropdown -->
          <div class="role-row">
            <label class="role-checkbox">
              <input type="checkbox" v-model="isTrainer" />
              <span>Übungsleiter</span>
            </label>

            <div class="role-dropdown-wrapper">
              <div v-if="isTrainer" class="dropdown">
                <div class="dropdown-header">Abteilung</div>
                <div class="dropdown-list">
                  <div
                      v-for="dept in departments"
                      :key="dept"
                      class="dropdown-item"
                      :class="{
                      'dropdown-item--selected': trainerDepartments.includes(
                        dept
                      ),
                    }"
                      @click="toggleTrainerDepartment(dept)"
                  >
                    {{ dept }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>

        <v-card-actions class="pa-0 mt-4 d-flex justify-center">
          <v-btn
              color="primary"
              class="mx-auto submit-btn"
              style="min-width:160px"
              type="submit"
          >
            Benutzer erstellen
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </div>
</template>

<style scoped>
.auth-page {
  max-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  box-sizing: border-box;
  overflow: hidden;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  border-radius: 12px;
}

.submit-btn {
  font-weight: 600;
}

.role-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 6px;
}

.role-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  min-width: 160px;
  font-weight: 500;
}

.role-checkbox input[type='checkbox'] {
  width: 16px;
  height: 16px;
  accent-color: #1976d2;
}

.role-dropdown-wrapper {
  flex: 1;
}

.dropdown {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 6px 8px;
  max-height: 100px;
  overflow-y: auto;
  background-color: #ffffff;
}

.dropdown-header {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #6b7280;
  margin-bottom: 4px;
}

.dropdown-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.9rem;
  padding: 2px 4px;
  border-radius: 4px;
  cursor: pointer;
  border: 1px solid transparent;
}

.dropdown-item--selected {
  border: 1px solid #1976d2;
  background-color: rgba(25, 118, 210, 0.08);
  color: #0b4a8a;
  font-weight: 600;
}
</style>
