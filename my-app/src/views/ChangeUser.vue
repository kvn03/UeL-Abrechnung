<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import type { UserDto, UpdateUserRolesPayload, DepartmentDto } from '../services/users';
import { fetchUsers, updateUserRoles } from '../services/users';
import axios from 'axios';

const router = useRouter();

const users = ref<UserDto[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const globalSaving = ref(false);

// Suchbegriff
const searchTerm = ref('');

// Aktueller Benutzer (aus Dashboard-API)
const currentUserId = ref<number | null>(null);

// Abteilungen aus der DB
const departments = ref<DepartmentDto[]>([]);

// Edit-Status pro User (damit man togglen kann, ohne sofort zu speichern)
interface EditState {
  isAdmin: boolean;
  isGeschaeftsstelle: boolean;
  departmentHeadIds: number[];
  trainerIds: number[];
  saving: boolean;
}

const editStates = ref<Record<number, EditState>>({});

const initEditStateForUser = (user: UserDto) => {
  editStates.value[user.id] = {
    isAdmin: user.isAdmin,
    isGeschaeftsstelle: user.isGeschaeftsstelle,
    departmentHeadIds: user.departmentHeadDepartments?.map(d => d.id) ?? [],
    trainerIds: user.trainerDepartments?.map(d => d.id) ?? [],
    saving: false,
  };
};

const loadCurrentUser = async () => {
  try {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      currentUserId.value = null;
      return;
    }
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    const response = await axios.get('http://127.0.0.1:8000/api/dashboard');
    currentUserId.value = response.data.user?.id ?? null;
  } catch (e) {
    console.error('Fehler beim Laden des aktuellen Benutzers:', e);
    currentUserId.value = null;
  }
};

const loadDepartments = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/abteilungen');
    departments.value = response.data as DepartmentDto[];
  } catch (e) {
    console.error('Konnte Abteilungen nicht laden. Ist das Backend gestartet?', e);
  }
};

const loadUsers = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      router.push({ name: 'Login' });
      return;
    }
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    const result = await fetchUsers();
    // Alphabetisch nach Vorname, dann Nachname sortieren
    users.value = [...result].sort((a, b) => {
      const aFirst = a.vorname?.toLocaleLowerCase() ?? '';
      const bFirst = b.vorname?.toLocaleLowerCase() ?? '';
      if (aFirst < bFirst) return -1;
      if (aFirst > bFirst) return 1;
      const aLast = a.name?.toLocaleLowerCase() ?? '';
      const bLast = b.name?.toLocaleLowerCase() ?? '';
      if (aLast < bLast) return -1;
      if (aLast > bLast) return 1;
      return 0;
    });

    editStates.value = {};
    for (const u of users.value) {
      initEditStateForUser(u);
    }
  } catch (e: any) {
    console.error('Fehler beim Laden der Benutzer:', e);
    error.value = 'Konnte Benutzerliste nicht laden.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await Promise.all([loadCurrentUser(), loadDepartments(), loadUsers()]);
});

const buildPayload = (userId: number): UpdateUserRolesPayload => {
  const state = editStates.value[userId];
  if (!state) {
    throw new Error(`Kein EditState für User ${userId} vorhanden`);
  }
  return {
    isAdmin: state.isAdmin,
    isGeschaeftsstelle: state.isGeschaeftsstelle,
    roles: {
      departmentHead: state.departmentHeadIds,
      trainer: state.trainerIds,
    },
  };
};

// Abteilungsleiter sollen nur eine einzige Abteilung haben
const onDepartmentHeadChange = (userId: number, value: number[] | number) => {
  const state = editStates.value[userId];
  if (!state) return;

  // Immer als Array behandeln und nur gültige Nummern übernehmen
  const valuesArray: number[] = [];
  if (Array.isArray(value)) {
    for (const v of value) {
      if (typeof v === 'number') valuesArray.push(v);
    }
  } else if (typeof value === 'number') {
    valuesArray.push(value);
  }

  // Nur das erste ausgewählte Element erlauben
  state.departmentHeadIds = valuesArray.length > 0 ? [valuesArray[0] as number] : [];

  markDirty(userId);
};

// Markiert einen Benutzer als "dirty" (geändert), um das globale Speichern zu ermöglichen
const markDirty = (userId: number) => {
  const state = editStates.value[userId];
  if (!state) return;
  // Hier könnte man eine spezifische Logik einfügen, falls nötig
};

// Gefilterte Benutzerliste anhand der Suchleiste
const filteredUsers = computed(() => {
  const term = searchTerm.value.trim().toLocaleLowerCase();

  // aktuellen Benutzer aus der Liste entfernen
  const baseUsers = users.value.filter((u) => {
    if (currentUserId.value === null) return true;
    return u.id !== currentUserId.value;
  });

  if (!term) return baseUsers;

  return baseUsers.filter((u) => {
    const first = u.vorname?.toLocaleLowerCase() ?? '';
    const last = u.name?.toLocaleLowerCase() ?? '';
    const mail = u.email?.toLocaleLowerCase() ?? '';
    return (
      first.includes(term) ||
      last.includes(term) ||
      mail.includes(term)
    );
  });
});

// Speichert alle Änderungen für alle Benutzer
const saveAllChanges = async () => {
  globalSaving.value = true;
  error.value = null;
  successMessage.value = null;
  try {
    for (const user of users.value) {
      // Eigenen Account überspringen
      if (currentUserId.value !== null && user.id === currentUserId.value) {
        continue;
      }
      const state = editStates.value[user.id];
      if (state) {
        const payload = buildPayload(user.id);
        await updateUserRoles(user.id, payload);
      }
    }
    successMessage.value = 'Alle Änderungen erfolgreich gespeichert.';
    // Nach Erfolg nochmal laden, um Ansicht zu synchronisieren
    await loadUsers();
  } catch (e: any) {
    console.error('Fehler beim Speichern der Benutzerrollen:', e);
    error.value = 'Konnte Änderungen nicht speichern.';
  } finally {
    globalSaving.value = false;
  }
};
</script>

<template>
  <div class="page">
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h2>Benutzer verwalten</h2>
        <div class="text-subtitle-1 text-medium-emphasis">
          Rollenübersicht und -änderung für alle registrierten Benutzer.
        </div>
      </div>
    </div>

    <v-card class="pa-4" :loading="isLoading">
      <template v-if="error">
        <v-alert type="error" variant="tonal" class="mb-4">
          {{ error }}
        </v-alert>
      </template>

      <template v-if="successMessage">
        <v-alert type="success" variant="tonal" class="mb-4">
          {{ successMessage }}
        </v-alert>
      </template>

      <template v-if="isLoading">
        <div class="d-flex justify-center my-6">
          <v-progress-circular indeterminate color="primary" />
        </div>
      </template>

      <template v-else>
        <div v-if="!users.length" class="text-medium-emphasis">
          Keine Benutzer gefunden.
        </div>

        <div v-else>
          <!-- Suchleiste -->
          <div class="mb-4 search-bar">
            <v-text-field
              v-model="searchTerm"
              label="Benutzer suchen"
              prepend-inner-icon="mdi-magnify"
              density="comfortable"
              variant="outlined"
              clearable
              hide-details
            />
          </div>

          <div class="user-table-wrapper">
            <table class="user-table">
              <thead>
              <tr>
                <th>Vorname</th>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Admin</th>
                <th>Geschäftsstelle</th>
                <th>Abteilungsleiter in</th>
                <th>Übungsleiter in</th>
              </tr>
              </thead>
              <tbody>
              <tr
                v-for="user in filteredUsers"
                :key="user.id"
              >
                <td>{{ user.vorname }}</td>
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>

                <td>
                  <v-select
                    v-model="editStates[user.id]!.isAdmin"
                    :items="[{ label: 'Ja', value: true }, { label: 'Nein', value: false }] as const"
                    item-title="label"
                    item-value="value"
                    density="compact"
                    hide-details
                    class="role-select"
                    @update:model-value="markDirty(user.id)"
                  />
                </td>

                <td>
                  <v-select
                    v-model="editStates[user.id]!.isGeschaeftsstelle"
                    :items="[{ label: 'Ja', value: true }, { label: 'Nein', value: false }] as const"
                    item-title="label"
                    item-value="value"
                    density="compact"
                    hide-details
                    class="role-select"
                    @update:model-value="markDirty(user.id)"
                  />
                </td>

                <td>
                  <v-select
                    v-model="editStates[user.id]!.departmentHeadIds"
                    :items="departments"
                    item-title="name"
                    item-value="id"
                    chips
                    density="compact"
                    hide-details
                    placeholder="Keine"
                    class="dept-select"
                    @update:model-value="onDepartmentHeadChange(user.id, $event)"
                  />
                </td>

                <td>
                  <v-select
                    v-model="editStates[user.id]!.trainerIds"
                    :items="departments"
                    item-title="name"
                    item-value="id"
                    chips
                    multiple
                    density="compact"
                    hide-details
                    placeholder="Keine"
                    class="dept-select"
                    @update:model-value="markDirty(user.id)"
                  />
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td colspan="7" class="text-medium-emphasis">
                  Keine Benutzer für den Suchbegriff gefunden.
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-end mt-4">
            <v-btn
              color="primary"
              :loading="globalSaving"
              @click="saveAllChanges"
            >
              Änderungen speichern
            </v-btn>
          </div>
        </div>
      </template>
    </v-card>
  </div>
</template>

<style scoped>
.user-table-wrapper {
  overflow-x: auto;
}

.user-table {
  width: 100%;
  border-collapse: collapse;
}

.user-table th,
.user-table td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem 0.75rem;
  text-align: left;
  vertical-align: middle;
}

.user-table thead th {
  background-color: #f5f5f5;
  font-weight: 600;
}

.role-select,
.dept-select {
  min-width: 120px;
}

.user-table tr:nth-child(even) {
  background-color: #fafafa;
}

.search-bar {
  max-width: 320px;
}

.is-current-user {
  opacity: 0.6;
}
</style>
