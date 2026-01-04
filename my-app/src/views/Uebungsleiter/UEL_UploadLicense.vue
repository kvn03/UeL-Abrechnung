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
      <v-card-title class="pa-0 mb-4">
        <h3 class="ma-0">Lizenzdaten angeben</h3>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-form>
          <div class="field-grid">
            <v-text-field
                v-model="lizenznummer"
                label="Lizenznummer"
                variant="outlined"
                density="comfortable"
                maxlength="50"
            />

            <v-text-field
                v-model="lizenzName"
                label="Lizenz-name"
                variant="outlined"
                density="comfortable"
                maxlength="100"
            />

            <v-text-field
                v-model="gueltigVon"
                label="Gültig von"
                type="date"
                variant="outlined"
                density="comfortable"
            />

            <v-text-field
                v-model="gueltigBis"
                label="Gültig bis"
                type="date"
                variant="outlined"
                density="comfortable"
            />
          </div>

          <div class="d-flex justify-end mt-6">
            <v-btn
                color="primary"
                prepend-icon="mdi-content-save"
                :loading="isSaving"
                @click="saveLicence"
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
// import axios from 'axios'

const router = useRouter()

function goBack() {
  router.push({ name: 'Dashboard' })
}

const lizenznummer = ref('')
const lizenzName = ref('')
const gueltigVon = ref('')
const gueltigBis = ref('')

const API_URL_PLACEHOLDER = import.meta.env.VITE_API_URL + '/api/uebungsleiter/lizenz'

// Optional: Loading/Error State für UX
const isSaving = ref(false)

async function saveLicence() {
  isSaving.value = true

  const payload = {
    lizenznummer: lizenznummer.value,
    lizenz_name: lizenzName.value,
    gueltig_von: gueltigVon.value,
    gueltig_bis: gueltigBis.value,
  }

  try {
    // Backend-Team trägt hier den finalen Endpoint ein.
    // await axios.post(API_URL_PLACEHOLDER, payload)
    console.log('Lizenzdaten speichern (Platzhalter):', API_URL_PLACEHOLDER, payload)

    // Optional: Nach Speichern zurück zum Dashboard?
    // goBack()
  } catch (error) {
    console.error('Fehler beim Speichern (Platzhalter):', error)
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

@media (max-width: 600px) {
  .field-grid {
    grid-template-columns: 1fr;
  }
}
</style>