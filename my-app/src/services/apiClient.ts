import axios from 'axios';

// Sicherstellen, dass wir eine saubere Base-URL haben
const envUrl = import.meta.env.VITE_API_URL;
// Wenn envUrl da ist, nimm es, sonst localhost. DANACH hängen wir /api an.
const baseURL = (envUrl || 'http://localhost:8000') + '/api';

const apiClient = axios.create({
    baseURL: baseURL,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// 1. Request Interceptor
apiClient.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// 2. Response Interceptor
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('auth_token');

            // TIPP: Leite den User hier am besten direkt zum Login weiter,
            // sonst bleibt er auf der Seite und wundert sich, warum nichts lädt.
            // window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default apiClient;