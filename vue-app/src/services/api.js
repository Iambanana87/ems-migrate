import axios from 'axios'

// Ensure we get the correct base API path whether in dev or prod
const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/gateway'

const api = axios.create({
  baseURL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// Request interceptor for injecting token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('ems_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}, error => {
  return Promise.reject(error)
})

// Response interceptor for consistent error handling
api.interceptors.response.use(
  response => response,
  error => {
    // Optionally handle 401 Unauthorized globally here
    if (error.response?.status === 401) {
      localStorage.removeItem('ems_token')
      // Eventbus or router redirect logic could go here
    }
    return Promise.reject(error)
  }
)

export default api
