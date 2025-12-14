// TypeScript
import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import EnterPassword from '../views/EnterPassword.vue'
import SetPassword from '../views/SetPassword.vue'
import NoPassword from '../views/NoPassword.vue'
import CreateUser from '../views/CreateUser.vue'
import Timesheet from "../views/Timesheet.vue";
import Submitted from "../views/Submitted.vue";
import Drafts from "../views/Drafts.vue";
import TimesheetSubmissions from "../views/TimesheetSubmissions.vue";
import AlReleaseSubmissions from '../views/AL_ReleaseSubmissions.vue'
import AllTimesheetSubmissions from "../views/AllTimesheetSubmissions.vue";
import TimesheetHistory from "../views/TimesheetHistory.vue";
import TimesheetsToPay from "../views/TimesheetsToPay.vue";

const routes: RouteRecordRaw[] = [
    { path: '/', redirect: { name: 'Login' } },
    { path: '/login', name: 'Login', component: Login },
    { path: '/enter-password', name: 'EnterPassword', component: EnterPassword },
    { path: '/dashboard', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/set-password', name: 'SetPassword', component: SetPassword },
    { path: '/no-password', name: 'NoPassword', component: NoPassword },
    { path: '/create-user', name: 'CreateUser', component: CreateUser, meta: { requiresAuth: true }},
    { path: '/timesheet', name: 'Timesheet', component: Timesheet, meta: { requiresAuth: true } },
    { path: '/drafts', name: 'Drafts', component: Drafts, meta: { requiresAuth: true } },
    { path: '/submitted', name: 'Submitted', component: Submitted, meta: { requiresAuth: true } },
    { path: '/timesheet-submissions', name: 'TimesheetSubmissions', component: TimesheetSubmissions, meta: { requiresAuth: true } },
    { path: '/release-submissions', name: 'ReleaseSubmissions', component: AlReleaseSubmissions, meta: { requiresAuth: true } },
    { path: '/all-timesheet-submissions', name: 'AllTimesheetSubmissions', component: AllTimesheetSubmissions, meta: { requiresAuth: true } },
    { path: '/timesheet-history', name: 'TimesheetHistory', component: TimesheetHistory, meta: { requiresAuth: true } },
    { path: '/timesheets-to-pay', name: 'TimesheetsToPay', component: TimesheetsToPay, meta: { requiresAuth: true } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

function isAuthenticated(): boolean {
    return !!localStorage.getItem('auth_token')
}

router.beforeEach((to, _from, next) => {
    if (to.name === 'Login' && isAuthenticated()) {
        return next({ name: 'Dashboard' })
    }

    // EnterPassword und NoPassword fÃ¼r eingeloggte sperren
    if (
        isAuthenticated() &&
        (to.name === 'EnterPassword' || to.name === 'NoPassword')
    ) {
        return next({ name: 'Dashboard' })
    }

    if ((to.meta as { requiresAuth?: boolean }).requiresAuth && !isAuthenticated()) {
        return next({ name: 'Login' })
    }

    next()
})

export default router