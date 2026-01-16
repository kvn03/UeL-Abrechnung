// UeL-Abrechnung/my-app/src/router/index.ts
import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import axios from 'axios'

import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import EnterPassword from '../views/EnterPassword.vue'
import SetPassword from '../views/SetPassword.vue'
import NoPassword from '../views/NoPassword.vue'
import CreateUser from '../views/Administrator/CreateUser.vue'
import UelTimesheet from '../views/Uebungsleiter/UEL_Timesheet.vue'
import Submitted from '../views/Submitted.vue'
import UelDrafts from '../views/Uebungsleiter/UEL_Drafts.vue'
import UelTimesheetSubmissions from '../views/Uebungsleiter/UEL_TimesheetSubmissions.vue'
import AlReleaseSubmissions from '../views/Abteilungsleiter/AL_ReleaseSubmissions.vue'
import GsAllTimesheetSubmissions from '../views/Geschaeftsstelle/GS_AllTimesheetSubmissions.vue'
import GsTimesheetHistory from '../views/Geschaeftsstelle/GS_TimesheetHistory.vue'
import AlTimesheetHistory from '../views/Abteilungsleiter/AL_TimesheetHistory.vue'
import GsTimesheetsToPay from '../views/Geschaeftsstelle/GS_TimesheetsToPay.vue'
import ChangeUser from '../views/ChangeUser.vue'
import UelEditProfile from "../views/Uebungsleiter/UEL_EditProfile.vue";
import UelUploadLicense from "../views/Uebungsleiter/UEL_UploadLicense.vue"
import AL_ChangeStundensatz from '../views/Abteilungsleiter/AL_ChangeStundensatz.vue'
import GS_ChangeStundensatz from '../views/Geschaeftsstelle/GS_ChangeStundensatz.vue'
import UelStundensatzUebersicht from '../views/Uebungsleiter/UEL_StundensatzUebersicht.vue'
import AdminDepartments from "../views/Administrator/AdminDepartments.vue"
import AdminZuschlag from "../views/Administrator/AdminZuschlag.vue"
import AdminLimits from "../views/Administrator/AdminLimits.vue";

const API_URL = import.meta.env.VITE_API_URL + '/api'

const routes: RouteRecordRaw[] = [
  { path: '/', redirect: { name: 'Login' } },
  { path: '/login', name: 'Login', component: Login },
  { path: '/enter-password', name: 'EnterPassword', component: EnterPassword },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/set-password', name: 'SetPassword', component: SetPassword },
  { path: '/no-password', name: 'NoPassword', component: NoPassword },
  { path: '/create-user', name: 'CreateUser', component: CreateUser, meta: { requiresAuth: true }},
  { path: '/timesheet', name: 'Timesheet', component: UelTimesheet, meta: { requiresAuth: true } },
  { path: '/drafts', name: 'Drafts', component: UelDrafts, meta: { requiresAuth: true } },
  { path: '/submitted', name: 'Submitted', component: Submitted, meta: { requiresAuth: true } },
  { path: '/timesheet-submissions', name: 'TimesheetSubmissions', component: UelTimesheetSubmissions, meta: { requiresAuth: true } },
  { path: '/release-submissions', name: 'ReleaseSubmissions', component: AlReleaseSubmissions, meta: { requiresAuth: true } },
  { path: '/all-timesheet-submissions', name: 'AllTimesheetSubmissions', component: GsAllTimesheetSubmissions, meta: { requiresAuth: true, requiresOffice: true } },
  { path: '/timesheet-history', name: 'TimesheetHistory', component: GsTimesheetHistory, meta: { requiresAuth: true, requiresOffice: true } },
  { path: '/timesheets-to-pay', name: 'TimesheetsToPay', component: GsTimesheetsToPay, meta: { requiresAuth: true, requiresOffice: true } },
  { path: '/change-user', name: 'ChangeUser', component: ChangeUser, meta: { requiresAuth: true } },
  { path: '/edit-profile', name: 'EditProfile', component: UelEditProfile, meta: { requiresAuth: true, requiresTrainer: true } },
  { path: '/upload-license', name: 'UelUploadLicense', component: UelUploadLicense, meta: { requiresAuth: true, requiresTrainer: true } },
    { path: '/abteilungsleiter/stundensaetze', name: 'ManageRates', component: AL_ChangeStundensatz, meta: { requiresAuth: true } },
    { path: '/geschaeftsstelle/stundensaetze', name: 'ManageAllRates', component: GS_ChangeStundensatz, meta: { requiresAuth: true }},
    {path: '/uebungsleiter/stundensaetze', name: 'MyRates', component: UelStundensatzUebersicht, meta: { requiresAuth: true }},
    {path: '/al-timesheet-history', name: 'ALTimesheetHistory', component: AlTimesheetHistory, meta: { requiresAuth: true }},
    {path: '/admin/abteilungen', name: 'AdminDepartments', component: AdminDepartments, meta: { requiresAuth: true }},
    {path: '/admin/zuschlaege', name: 'AdminZuschlag', component: AdminZuschlag, meta: {requiresAuth: true}},
    {path: '/limits', name: 'AdminLimits', component: AdminLimits, meta: { requiresAuth: true }},

]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

function isAuthenticated(): boolean {
  return !!localStorage.getItem('auth_token')
}

let cachedUser: any | null = null
let userLoaded = false

async function fetchCurrentUser() {
  if (userLoaded) return cachedUser

  const token = localStorage.getItem('auth_token')
  if (!token) {
    cachedUser = null
    userLoaded = true
    return null
  }

  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

  try {
    const response = await axios.get(`${API_URL}/dashboard`)
    cachedUser = response.data.user
  } catch (e) {
    console.error('Fehler beim Laden des aktuellen Users im Router-Guard:', e)
    cachedUser = null
  } finally {
    userLoaded = true
  }

  return cachedUser
}

let cachedPermissions: any[] | null = null
let permissionsLoaded = false

async function fetchCurrentPermissions() {
  if (permissionsLoaded) return cachedPermissions

  const token = localStorage.getItem('auth_token')
  if (!token) {
    cachedPermissions = null
    permissionsLoaded = true
    return null
  }

  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

  try {
    const response = await axios.get(`${API_URL}/dashboard`)
    cachedPermissions = response.data.berechtigungen ?? []
  } catch (e) {
    console.error('Fehler beim Laden der Berechtigungen im Router-Guard:', e)
    cachedPermissions = null
  } finally {
    permissionsLoaded = true
  }

  return cachedPermissions
}

function isTrainerRole(rolle: unknown): boolean {
  return rolle === 'Uebungsleiter' || rolle === 'Übungsleiter'
}

router.beforeEach(async (to, _from, next) => {
  const requiresAuth = (to.meta as { requiresAuth?: boolean }).requiresAuth
  const requiresOffice = (to.meta as { requiresOffice?: boolean }).requiresOffice
  const requiresTrainer = (to.meta as { requiresTrainer?: boolean }).requiresTrainer

  if (to.name === 'Login' && isAuthenticated()) {
    return next({ name: 'Dashboard' })
  }

  if (
      isAuthenticated() &&
      (to.name === 'EnterPassword' || to.name === 'NoPassword')
  ) {
    return next({ name: 'Dashboard' })
  }

  if (requiresAuth && !isAuthenticated()) {
    return next({ name: 'Login' })
  }

  // Übungsleiter-Schutz (nur wenn gefordert)
  if (requiresTrainer) {
    const permissions = await fetchCurrentPermissions()
    const ok = Array.isArray(permissions) && permissions.some(p => isTrainerRole(p?.rolle))
    if (!ok) {
      return next({ name: 'Dashboard' })
    }
  }

  if (!requiresOffice) {
    return next()
  }

  const currentUser = await fetchCurrentUser()

  if (!currentUser || currentUser.isGeschaeftsstelle !== true) {
    return next({ name: 'Dashboard' })
  }

  return next()
})

export default router
