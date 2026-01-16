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
// NEU: Variable für das Limit
const currentLimit = ref<number>(0)
const usedAmount = ref<number>(0) // <--- NEU
const isLoading = ref(true)
const API_URL = import.meta.env.VITE_API_URL + '/api'

// --- Computed Properties für die Anzeige-Steuerung ---

const isDepartmentHead = computed(() => {
  return permissions.value.some(p => p.rolle === 'Abteilungsleiter');
});

const isTrainer = computed(() => {
  return permissions.value.some(p => p.rolle === 'Uebungsleiter' || p.rolle === 'Übungsleiter');
});

const isAdmin = computed(() => user.value?.isAdmin === true)

const isOfficeManager = computed(() => {
  if (user.value?.isGeschaeftsstelle) return true
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

    // NEU: Limit aus der API lesen (falls vorhanden, sonst 0)
    currentLimit.value = response.data.limit || 0
    usedAmount.value = response.data.usedAmount || 0 // <--- NEU

  } catch (error) {
    console.error('Fehler beim Laden:', error)
    handleLogout()
  } finally {
    isLoading.value = false
  }
})

const limitProgress = computed(() => {
  if (currentLimit.value === 0) return 0
  return Math.min((usedAmount.value / currentLimit.value) * 100, 100)
})
const progressColor = computed(() => {
  if (limitProgress.value > 90) return 'error'
  if (limitProgress.value > 75) return 'warning'
  return 'primary'
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

// NEU: Helper für Währungsformatierung
function formatCurrency(val: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val)
}
</script>

<template>
  <div class="page">
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h2>Dashboard</h2>
        <div v-if="user" class="text-subtitle-1 text-medium-emphasis">
          Willkommen zurück, {{ user.vorname }} {{ user.name }}!
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

        <v-card
            v-if="isTrainer && currentLimit > 0"
            variant="outlined"
            class="mb-6 bg-grey-lighten-5"
        >
          <v-card-text>
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="text-subtitle-1 font-weight-bold text-medium-emphasis">
                Steuerfreibetrag ({{ new Date().getFullYear() }})
              </div>
              <div class="text-subtitle-1 font-weight-bold">
                {{ formatCurrency(usedAmount) }} / {{ formatCurrency(currentLimit) }}
              </div>
            </div>

            <v-progress-linear
                :model-value="limitProgress"
                :color="progressColor"
                height="24"
                rounded
            >
              <template v-slot:default="{ value }">
                <strong class="text-white text-caption" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                  {{ Math.ceil(value) }}% ausgeschöpft
                </strong>
              </template>
            </v-progress-linear>

            <div class="text-caption mt-3 text-medium-emphasis d-flex align-center">
              <v-icon v-if="usedAmount >= currentLimit" icon="mdi-alert-circle" color="error" size="small" class="mr-2"></v-icon>
              <v-icon v-else icon="mdi-information-outline" size="small" class="mr-2"></v-icon>

              <span v-if="usedAmount >= currentLimit" class="text-red font-weight-medium">
                Du hast deinen Freibetrag für dieses Jahr erreicht oder überschritten.
              </span>
              <span v-else>
                Du hast noch <b>{{ formatCurrency(currentLimit - usedAmount) }}</b> offen.
              </span>
            </div>
          </v-card-text>
        </v-card>

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

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-account-edit"
              @click="router.push({ name: 'EditProfile' })"
          >
            persönliche Daten bearbeiten
          </v-btn>
          <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-certificate-outline"
              @click="router.push({ name: 'UelUploadLicense' })"
          >
            Lizenz angeben
          </v-btn>
          <v-btn
              class="w-100"
              color="primary"
              variant="tonal"
              prepend-icon="mdi-cash-clock"
              @click="router.push({ name: 'MyRates' })"
          >
            Meine Stundensätze
          </v-btn>
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
          <v-btn
              class="w-100"
              color="secondary"
              variant="tonal"
              prepend-icon="mdi-cash-edit"
              @click="router.push({ name: 'ManageRates' })"
          >
            Stundensätze verwalten
          </v-btn>
          <v-btn
              color="blue-grey"
              variant="tonal"
              prepend-icon="mdi-archive"
              class="w-100 mb-2"
              @click="router.push({ name: 'ALTimesheetHistory' })"
          >
            Abrechnungshistorie
          </v-btn>

          <div></div>
        </template>


        <template v-if="isOfficeManager">
          <div class="section-title mt-6">Geschäftsstelle</div>

          <v-btn
              class="w-100"
              color="info"
              prepend-icon="mdi-check-decagram-outline"
              @click="router.push({ name: 'AllTimesheetSubmissions' })"
          >
            Abrechnungen freigeben
          </v-btn>

          <v-btn
              class="w-100"
              color="success"
              prepend-icon="mdi-cash-multiple"
              @click="router.push({ name: 'TimesheetsToPay' })"
          >
            Zu bezahlende Abrechnungen
          </v-btn>

          <v-btn
              class="w-100"
              color="secondary"
              prepend-icon="mdi-history"
              @click="router.push({ name: 'TimesheetHistory' })"
          >
            Abrechnungshistorie
          </v-btn>
          <v-btn
              class="w-100"
              color="secondary"
              variant="tonal"
              prepend-icon="mdi-cash-edit"
              @click="router.push({ name: 'ManageAllRates' })"
          >
            Alle Stundensätze verwalten
          </v-btn>

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-account-cog"
              @click="router.push({ name: 'ChangeUser' })"
          >
            User verwalten
          </v-btn>
          <v-btn
              class="w-100"
              color="secondary"
              variant="tonal"
              prepend-icon="mdi-cash-lock"
              @click="router.push({ name: 'AdminLimits' })"
          >
            Übungsleiterpauschale verwalten
          </v-btn>
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

          <v-btn
              class="w-100"
              color="primary"
              prepend-icon="mdi-account-cog"
              :to="{ name: 'ChangeUser' }"
          >
            User verwalten
          </v-btn>
          <v-btn
              class="w-100"
              color="secondary"
              prepend-icon="mdi-domain"
              @click="router.push({ name: 'AdminDepartments' })"
          >
            Abteilungen verwalten
          </v-btn>
          <v-btn
              class="w-100"
              color="secondary"
              variant="tonal"
              prepend-icon="mdi-percent"
              @click="router.push({ name: 'AdminZuschlag' })"
          >
            Zuschläge verwalten
          </v-btn>

          <v-btn
              class="w-100"
              color="secondary"
              variant="tonal"
              prepend-icon="mdi-cash-lock"
              @click="router.push({ name: 'AdminLimits' })"
          >
            Übungsleiterpauschale verwalten
          </v-btn>
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
.border-success {
  border: 1px solid rgba(var(--v-theme-success), 0.5);
}
</style>