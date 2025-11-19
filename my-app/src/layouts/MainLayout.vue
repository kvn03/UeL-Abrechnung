<script setup lang="ts">
import { RouterView } from 'vue-router'
import logo from '../assets/ac-weinheim.png'
import avatar from '../assets/avatar.png'
</script>

<template>
  <v-app>
    <v-app-bar app color="white" height="64" elevation="1">
      <v-img
          :src="logo"
          alt="AC Weinheim"
          max-height="44"
          max-width="44"
          contain
          class="mr-4 rounded"
      />
      <v-toolbar-title class="text-h6 text-primary font-weight-medium">
        Dashboard
      </v-toolbar-title>

      <v-spacer></v-spacer>

      <v-btn icon>
        <v-avatar size="40">
          <v-img :src="avatar" alt="Profil" />
        </v-avatar>
      </v-btn>
    </v-app-bar>

    <v-main>
      <v-container fluid class="pa-4" style="max-width: 1200px; margin: 0 auto">
        <RouterView />
      </v-container>
    </v-main>

    <v-footer app height="56" class="pa-2">
      <v-row justify="center" align="center" style="width: 100%">
        <span class="text-subtitle-2" style="color: rgba(107, 114, 128, 1)">
          Programmiert in Kooperation mit der FH Aachen
        </span>
      </v-row>
    </v-footer>
  </v-app>
</template>

<style>
.v-application {
  background: #f5f7fa;
  color: #111827;
}
.rounded {
  border-radius: 6px;
  background: white;
  padding: 4px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
</style>
