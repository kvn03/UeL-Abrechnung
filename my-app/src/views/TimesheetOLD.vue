<script setup lang="ts">
import { ref } from 'vue'
import { submitTimesheet } from '../services/timesheets'

const date = ref('')
const hours = ref<number | null>(null)
const note = ref('')

async function save() {
  if (!date.value || !hours.value) return
  await submitTimesheet({ date: date.value, hours: Number(hours.value), note: note.value })
  date.value = ''
  hours.value = null
  note.value = ''
  alert('Saved (mock)')
}
</script>

<template>
  <div class="page">
    <h2>Timesheet</h2>
    <label>Date <input v-model="date" type="date" /></label>
    <label>Hours <input v-model="hours" type="number" min="0" step="0.25" /></label>
    <label>Note <textarea v-model="note"></textarea></label>
    <button @click="save">Save</button>
  </div>
</template>