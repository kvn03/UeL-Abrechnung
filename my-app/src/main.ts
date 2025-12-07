import { createApp } from 'vue'
import axios from "axios";
import App from './App.vue'
import router from './router'
import { createVuetify } from 'vuetify'
import './styles/main.scss'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'

const vuetify = createVuetify({
    components,
    directives,
})

createApp(App).use(router).use(vuetify).mount('#app')

const token = localStorage.getItem('auth_token')
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

app.mount('#app')
