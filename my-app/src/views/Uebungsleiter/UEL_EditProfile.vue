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
      <v-progress-linear
          v-if="isLoading"
          indeterminate
          color="primary"
          absolute
          top
      ></v-progress-linear>

      <v-card-title class="pa-0 mb-4">
        <h3 class="ma-0">Stammdaten bearbeiten</h3>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form ref="form" @submit.prevent="saveProfile">
          <div class="field-grid">
            <v-text-field
                v-model="plz"
                label="PLZ"
                variant="outlined"
                density="comfortable"
                maxlength="10"
                :disabled="isLoading"
                hide-details="auto"
                :rules="[v => !!v || 'Pflichtfeld']"
            />
            <v-text-field
                v-model="ort"
                label="Ort"
                variant="outlined"
                density="comfortable"
                :disabled="isLoading"
                hide-details="auto"
                :rules="[v => !!v || 'Pflichtfeld']"
            />
            <v-text-field
                v-model="strasse"
                label="Straße"
                variant="outlined"
                density="comfortable"
                :disabled="isLoading"
                hide-details="auto"
                :rules="[v => !!v || 'Pflichtfeld']"
            />
            <v-text-field
                v-model="hausnummer"
                label="Hausnummer"
                variant="outlined"
                density="comfortable"
                class="hausnr"
                :disabled="isLoading"
                hide-details="auto"
                :rules="[v => !!v || 'Pflichtfeld']"
            />
          </div>

          <v-text-field
              v-model="iban"
              label="IBAN"
              variant="outlined"
              density="comfortable"
              class="mt-4"
              :disabled="isLoading"
              :rules="ibanRules"
              validate-on="blur"
              hint="Format: DEXX XXXX XXXX XXXX XXXX XX"
          />

          <v-alert
              v-if="errorMessage"
              type="error"
              variant="tonal"
              class="mt-4"
              closable
              @click:close="errorMessage = null"
          >
            {{ errorMessage }}
          </v-alert>

          <v-alert
              v-if="successMessage"
              type="success"
              variant="tonal"
              class="mt-4"
              closable
              @click:close="successMessage = null"
          >
            {{ successMessage }}
          </v-alert>

          <div class="d-flex justify-end mt-6">
            <v-btn
                color="primary"
                prepend-icon="mdi-content-save"
                type="submit"
                :loading="isSaving"
                :disabled="isLoading"
            >
              Speichern
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import type { VForm } from 'vuetify/components'

const router = useRouter()
const form = ref<VForm | null>(null) // Referenz zum Formular

// --- State ---
const plz = ref('')
const ort = ref('')
const strasse = ref('')
const hausnummer = ref('')
const iban = ref('')

const isLoading = ref(false)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const API_URL = 'http://127.0.0.1:8000/api/uebungsleiter/profil'

// --- IBAN Validierungs-Logik (Modulo 97 Algorithmus) ---
function checkIBAN(input: string): boolean | string {
  // 1. Leerzeichen entfernen und Großschreibung
  const iban = input.toUpperCase().replace(/\s/g, '')

  // 2. Grobe Musterprüfung (Ländercode + Ziffern + Alphanumerisch)
  // Mindestlänge 15 (Norwegen), Maximallänge 34
  if (!/^([A-Z]{2}[0-9]{2}[A-Z0-9]{1,30})$/.test(iban)) {
    return 'Ungültiges Format (Ländercode am Anfang?)'
  }

  // 3. Ländercode und Prüfziffer ans Ende verschieben
  const rearranged = iban.substring(4) + iban.substring(0, 4)

  // 4. Buchstaben in Zahlen umwandeln (A=10, B=11, ..., Z=35)
  const numeric = rearranged.split('').map(char => {
    const code = char.charCodeAt(0)
    // Wenn Buchstabe (A-Z), dann Code-55, sonst die Ziffer selbst
    if (code >= 65 && code <= 90) {
      return code - 55
    }
    return char
  }).join('')

  // 5. Modulo 97 Berechnung mit BigInt (da Zahl zu groß für normalen Integer)
  const isValid = BigInt(numeric) % 97n === 1n

  return isValid || 'Prüfziffer ist falsch (Tippfehler?)'
}

const ibanRules = [
  (v: string) => !!v || 'IBAN ist ein Pflichtfeld',
  (v: string) => checkIBAN(v) === true || (checkIBAN(v) as string)
]

function goBack() {
  router.push({ name: 'Dashboard' })
}

// --- 1. Daten laden beim Start ---
onMounted(async () => {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await axios.get(API_URL)
    const data = response.data

    plz.value = data.plz || ''
    ort.value = data.ort || ''
    strasse.value = data.strasse || ''
    hausnummer.value = data.hausnummer || ''
    iban.value = data.iban || ''

  } catch (error: any) {
    console.error('Fehler beim Laden:', error)
    errorMessage.value = 'Profildaten konnten nicht geladen werden.'
  } finally {
    isLoading.value = false
  }
})

// --- 2. Daten speichern ---
async function saveProfile() {
  // Zuerst Formular validieren
  const { valid } = await form.value?.validate() || { valid: false }

  if (!valid) {
    errorMessage.value = 'Bitte korrigiere die rot markierten Felder.'
    return
  }

  isSaving.value = true
  errorMessage.value = null
  successMessage.value = null

  const payload = {
    plz: plz.value,
    ort: ort.value,
    strasse: strasse.value,
    hausnummer: hausnummer.value,
    iban: iban.value.replace(/\s/g, ''), // Leerzeichen vor dem Senden entfernen
  }

  try {
    await axios.post(API_URL, payload)
    successMessage.value = 'Daten erfolgreich gespeichert!'

    setTimeout(() => { successMessage.value = null }, 3000)

  } catch (error: any) {
    console.error('Fehler beim Speichern:', error)
    if (error.response && error.response.data && error.response.data.message) {
      errorMessage.value = 'Fehler: ' + error.response.data.message
    } else {
      errorMessage.value = 'Speichern fehlgeschlagen. Bitte überprüfe deine Eingaben.'
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.page {
  padding: 24px;
  max-width: 640px;
  margin: 0 auto;
}

.field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}

.hausnr {
  max-width: 150px;
}

@media (max-width: 600px) {
  .field-grid {
    grid-template-columns: 1fr;
  }
}
</style>