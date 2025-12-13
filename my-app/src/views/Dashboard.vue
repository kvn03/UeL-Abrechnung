<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

type Berechtigung = {
  rolle: string
  abteilung: string
}

const router = useRouter()
const user = ref<any>(null)
const permissions = ref<Berechtigung[]>([])
const isLoading = ref(true)
const API_URL = 'http://127.0.0.1:8000/api'

// --- Computed Properties für die Anzeige-Steuerung ---

// Prüft, ob der User IRGENDEINE Rolle "Abteilungsleiter" hat
const isDepartmentHead = computed(() => {
  return permissions.value.some(p => p.rolle === 'Abteilungsleiter') || user.value?.isAdmin
})

// Prüft, ob der User IRGENDEINE Rolle "Übungsleiter" (oder "Uebungsleiter") hat
const isTrainer = computed(() => {
  return permissions.value.some(p => p.rolle === 'Uebungsleiter' || p.rolle === 'Übungsleiter') || user.value?.isAdmin
})

// Admin Check
const isAdmin = computed(() => user.value?.isAdmin === true)

// Geschäftsstelle Check (aus dem User-Objekt oder Permissions, je nachdem wo es herkommt)
const isOfficeManager = computed(() => {
  // Option A: Es kommt direkt als Boolean im User-Objekt (wie in deinem Backend-Controller definiert)
  if (user.value?.isGeschaeftsstelle) return true

  // Option B: Fallback, falls es doch über Rollen kommt
  return permissions.value.some(p => p.rolle === 'Geschäftsstelle')
})

onMounted(async () => {
  const token = localStorage.getItem('auth_token')

  if (!token) {
    router.push({ name: 'Login' })
    return
  }

  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

  try {
    const response = await axios.get(`${API_URL}/dashboard`)
    user.value = response.data.user
    permissions.value = response.data.berechtigungen
  } catch (error) {
    console.error('Fehler beim Laden:', error)
    handleLogout()
  } finally {
    isLoading.value = false
  }
})

const handleLogout = async () => {
  try {
    await axios.post(`${API_URL}/logout`)
  } catch (error) {
    console.warn('Logout Backend-Warnung:', error)
  } finally {
    localStorage.removeItem('auth_token')
    delete axios.defaults.headers.common['Authorization']
    router.push({ name: 'Login' })
  }
}
</script>

<template>
  <div class="page">
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h2>Dashboard</h2>
        <div v-if="user" class="text-subtitle-1 text-medium-emphasis">
          Hallo, {{ user.vorname }} {{ user.name }}!
        </div>
        <div v-else-if="isLoading" class="text-caption">
          Lade Benutzerdaten...
        </div>
      </div>

      <v-btn
          color="error"
          variant="tonal"
          prepend-icon="mdi-logout"
          @click="handleLogout"
      >
        Abmelden
      </v-btn>
    </div>

    <v-card class="pa-4" :loading="isLoading">
      <template v-if="user">
        <p>Du bist eingeloggt als: <strong>{{ user.email }}</strong></p>

        <div class="mt-2 text-body-2 text-medium-emphasis">
          <v-chip v-if="user.isAdmin" color="red" size="small" class="mr-2" label>Admin</v-chip>
          <v-chip v-if="user.isGeschaeftsstelle" color="blue" size="small" class="mr-2" label>Geschäftsstelle</v-chip>
        </div>

        <v-divider class="my-4"></v-divider>

        <div v-if="permissions.length > 0">
          <h3 class="text-subtitle-1 font-weight-bold mb-2">Deine Abteilungen</h3>
          <ul class="ml-4">
            <li v-for="(item, index) in permissions" :key="index" class="mb-1">
              <strong>{{ item.abteilung }}</strong>:
              {{ item.rolle === 'Uebungsleiter' ? 'Übungsleiter' : item.rolle }}
            </li>
          </ul>
        </div>
        <p v-else-if="!user.isAdmin && !user.isGeschaeftsstelle" class="text-caption text-medium-emphasis">
          Keine Abteilungen zugewiesen.
        </p>
      </template>

      <div class="btn-grid mt-4">

        <template v-if="isTrainer">
          <div class="section-title">Übungsleiter Bereich</div>

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-clock-outline"
              @click="router.push({ name: 'Timesheet' })"
          >
            Stunde erfassen
          </v-btn>

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-file-edit-outline"
              @click="router.push({ name: 'Drafts' })"
          >
            Übersicht Entwürfe
          </v-btn>

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-format-list-bulleted"
              @click="router.push({ name: 'TimesheetSubmissions' })"
          >
            Übersicht Abrechnungen
          </v-btn>

          <div></div>
        </template>


        <template v-if="isDepartmentHead">
          <div class="section-title mt-6">Abteilungsleiter Bereich</div>

          <v-btn
              class="w-100"
              color="secondary"
              prepend-icon="mdi-check-decagram-outline"
              @click="router.push({ name: 'ReleaseSubmissions' })"
          >
            Abrechnungen freigeben
          </v-btn>

          <div></div>
        </template>


        <template v-if="isOfficeManager || isAdmin">
          <div class="section-title mt-6">Geschäftsstelle</div>

          <v-btn
              class="w-100"
              color="info"
              prepend-icon="mdi-file-document-outline"
              @click="router.push({ name: 'AllTimesheetSubmissions' })"
          >
            Alle Abrechnungen
          </v-btn>
          <div></div>
        </template>


        <template v-if="isAdmin">
          <div class="section-title mt-6">Admin Bereich</div>

          <v-btn
              class="w-100"
              color="warning"
              prepend-icon="mdi-account-plus"
              :to="{ name: 'CreateUser' }"
          >
            User erstellen
          </v-btn>
          <div></div>
        </template>

      </div>
    </v-card>
  </div>
</template>

<style scoped>
.btn-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr); /* 2 Spalten */
  row-gap: 12px;
  column-gap: 16px;
  align-items: center;
}

/* Titel soll immer über beide Spalten gehen */
.section-title {
  grid-column: 1 / -1;
  font-size: 0.95rem;
  font-weight: 600;
  color: rgba(0,0,0,0.7);
  margin-top: 24px;
  margin-bottom: 8px;
  border-bottom: 1px solid rgba(0,0,0,0.1);
  padding-bottom: 4px;
}

/* Erster Titel braucht keinen Margin oben */
.section-title:first-child {
  margin-top: 0;
}

@media (max-width: 600px) {
  .btn-grid {
    grid-template-columns: 1fr; /* Auf Handy nur 1 Spalte */
  }
}
</style>