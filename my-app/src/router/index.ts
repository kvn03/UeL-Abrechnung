// TypeScript
import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import axios from 'axios'

import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import EnterPassword from '../views/EnterPassword.vue'
import SetPassword from '../views/SetPassword.vue'
import NoPassword from '../views/NoPassword.vue'
import CreateUser from '../views/CreateUser.vue'
import UelTimesheet from '../views/Uebungsleiter/UEL_Timesheet.vue'
import Submitted from '../views/Submitted.vue'
import UelDrafts from '../views/Uebungsleiter/UEL_Drafts.vue'
import UelTimesheetSubmissions from '../views/Uebungsleiter/UEL_TimesheetSubmissions.vue'
import AlReleaseSubmissions from '../views/Abteilungsleiter/AL_ReleaseSubmissions.vue'
import GsAllTimesheetSubmissions from '../views/Geschaeftsstelle/GS_AllTimesheetSubmissions.vue'
import GsTimesheetHistory from '../views/Geschaeftsstelle/GS_TimesheetHistory.vue'
import GsTimesheetsToPay from '../views/Geschaeftsstelle/GS_TimesheetsToPay.vue'
import ChangeUser from '../views/ChangeUser.vue'

const API_URL = 'http://127.0.0.1:8000/api'

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

router.beforeEach(async (to, _from, next) => {
  const requiresAuth = (to.meta as { requiresAuth?: boolean }).requiresAuth
  const requiresOffice = (to.meta as { requiresOffice?: boolean }).requiresOffice

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