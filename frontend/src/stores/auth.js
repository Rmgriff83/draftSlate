import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const checked = ref(false)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/v1/auth/user')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      checked.value = true
    }
  }

  async function login(credentials) {
    loading.value = true
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/api/v1/auth/login', credentials)
      await fetchUser()
      return { success: true }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Login failed',
      }
    } finally {
      loading.value = false
    }
  }

  async function register(data) {
    loading.value = true
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/api/v1/auth/register', data)
      await fetchUser()
      return { success: true }
    } catch (error) {
      return {
        success: false,
        errors: error.response?.data?.errors || {},
        message: error.response?.data?.message || 'Registration failed',
      }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/api/v1/auth/logout')
    } finally {
      user.value = null
    }
  }

  function $reset() {
    user.value = null
    checked.value = false
    loading.value = false
  }

  return {
    user,
    checked,
    loading,
    isAuthenticated,
    fetchUser,
    login,
    register,
    logout,
    $reset,
  }
})
