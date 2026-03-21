import axios from 'axios'

const api = axios.create({
  baseURL: '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// CSRF optimization: only fetch token when cookie is missing
api.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
    const hasToken = document.cookie.split(';').some((c) =>
      c.trim().startsWith('XSRF-TOKEN=')
    )
    if (!hasToken && !config.url.includes('csrf-cookie')) {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    }
  }
  return config
})

// Handle 401 → redirect to login
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const currentPath = window.location.pathname
      if (currentPath.startsWith('/app')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
