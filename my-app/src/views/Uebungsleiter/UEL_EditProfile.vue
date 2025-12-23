<!-- UeL-Abrechnung/my-app/src/views/Uebungsleiter/UEL_EditProfile.vue -->
<template>
  <div class="page">
    <v-card elevation="6" class="pa-4">
      <v-card-title class="pa-0 mb-4">
        <h3 class="ma-0">Daten bearbeiten</h3>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form>
          <div class="field-grid">
            <v-text-field
              v-model="plz"
              label="PLZ"
              variant="outlined"
              density="comfortable"
              maxlength="10"
            />
            <v-text-field
              v-model="ort"
              label="Ort"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-model="strasse"
              label="Straße"
              variant="outlined"
              density="comfortable"
            />
            <v-text-field
              v-model="hausnummer"
              label="Hausnummer"
              variant="outlined"
              density="comfortable"
              class="hausnr"
            />
          </div>

          <v-text-field
            v-model="iban"
            label="IBAN"
            variant="outlined"
            density="comfortable"
            class="mt-4"
          />

          <div class="d-flex justify-end mt-6">
            <v-btn color="primary" prepend-icon="mdi-content-save" @click="saveProfile">
              Speichern
            </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
// import axios from 'axios'

const plz = ref('')
const ort = ref('')
const strasse = ref('')
const hausnummer = ref('')
const iban = ref('')

// Platzhalter: Hier später den echten Backend-Endpoint eintragen
const API_URL_PLACEHOLDER = 'http://127.0.0.1:8000/api/uebungsleiter/profil'

// Optional: Loading/Error State für UX
const isSaving = ref(false)

async function saveProfile() {
  isSaving.value = true
  const payload = {
    plz: plz.value,
    ort: ort.value,
    strasse: strasse.value,
    hausnummer: hausnummer.value,
    iban: iban.value,
  }

  try {
    // Backend-Team trägt hier den finalen Endpoint ein.
    // await axios.post(API_URL_PLACEHOLDER, payload)
    console.log('Profil speichern (Platzhalter):', API_URL_PLACEHOLDER, payload)
    // Optional: Erfolgsmeldung per Snackbar/Dialog
  } catch (error) {
    console.error('Fehler beim Speichern (Platzhalter):', error)
    // Optional: Fehlermeldung anzeigen
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