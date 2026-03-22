import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/utils/api'

export const useLeagueStore = defineStore('league', () => {
  const myLeagues = ref([])
  const browseLeagues = ref([])
  const currentLeague = ref(null)
  const loading = ref(false)
  const pagination = ref(null)

  async function fetchMyLeagues() {
    loading.value = true
    try {
      const { data } = await api.get('/api/v1/leagues', {
        params: { my_leagues: true },
      })
      myLeagues.value = data.data
    } catch {
      myLeagues.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchBrowseLeagues(filters = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/api/v1/leagues', { params: filters })
      browseLeagues.value = data.data
      pagination.value = data.meta || null
    } catch {
      browseLeagues.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchLeague(id) {
    loading.value = true
    try {
      const { data } = await api.get(`/api/v1/leagues/${id}`)
      currentLeague.value = data.data
      return { success: true, data: data.data }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to load league',
      }
    } finally {
      loading.value = false
    }
  }

  async function createLeague(leagueData) {
    loading.value = true
    try {
      const { data } = await api.post('/api/v1/leagues', leagueData)
      myLeagues.value.push(data.data)
      return { success: true, data: data.data }
    } catch (error) {
      return {
        success: false,
        errors: error.response?.data?.errors || {},
        message: error.response?.data?.message || 'Failed to create league',
      }
    } finally {
      loading.value = false
    }
  }

  async function joinLeague(id, teamName) {
    loading.value = true
    try {
      const { data } = await api.post(`/api/v1/leagues/${id}/join`, {
        team_name: teamName,
      })
      myLeagues.value.push(data.data)
      return { success: true, data: data.data }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to join league',
      }
    } finally {
      loading.value = false
    }
  }

  async function leaveLeague(id) {
    loading.value = true
    try {
      await api.post(`/api/v1/leagues/${id}/leave`)
      myLeagues.value = myLeagues.value.filter((l) => l.id !== id)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to leave league',
      }
    } finally {
      loading.value = false
    }
  }

  async function joinByInviteCode(code) {
    loading.value = true
    try {
      const { data } = await api.get(`/api/v1/leagues/join/${code}`)
      return { success: true, data: data.data }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Invalid invite code',
      }
    } finally {
      loading.value = false
    }
  }

  async function updateLeague(id, leagueData) {
    loading.value = true
    try {
      const { data } = await api.put(`/api/v1/leagues/${id}`, leagueData)
      currentLeague.value = data.data
      const idx = myLeagues.value.findIndex((l) => l.id === id)
      if (idx !== -1) myLeagues.value[idx] = data.data
      return { success: true, data: data.data }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to update league',
      }
    } finally {
      loading.value = false
    }
  }

  async function cancelLeague(id) {
    loading.value = true
    try {
      await api.delete(`/api/v1/leagues/${id}`)
      myLeagues.value = myLeagues.value.filter((l) => l.id !== id)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to cancel league',
      }
    } finally {
      loading.value = false
    }
  }

  function $reset() {
    myLeagues.value = []
    browseLeagues.value = []
    currentLeague.value = null
    loading.value = false
    pagination.value = null
  }

  return {
    myLeagues,
    browseLeagues,
    currentLeague,
    loading,
    pagination,
    fetchMyLeagues,
    fetchBrowseLeagues,
    fetchLeague,
    createLeague,
    joinLeague,
    leaveLeague,
    joinByInviteCode,
    updateLeague,
    cancelLeague,
    $reset,
  }
})
