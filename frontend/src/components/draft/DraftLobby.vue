<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useDraftStore } from '@/stores/draft'
import { useLeagueStore } from '@/stores/league'

const props = defineProps({
  leagueId: { type: [String, Number], required: true },
})

const draft = useDraftStore()
const leagueStore = useLeagueStore()

const league = computed(() => leagueStore.currentLeague)
const nextDraftAt = computed(() => league.value?.next_draft_at)

// Lobby countdown to scheduled draft time
const lobbyCountdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const lobbyIsLive = ref(false)
let lobbyTimer = null
let pollTimer = null

function updateLobbyCountdown() {
  if (!nextDraftAt.value) return
  const diff = new Date(nextDraftAt.value).getTime() - Date.now()
  if (diff <= 0) {
    if (!lobbyIsLive.value) {
      lobbyIsLive.value = true
      startPolling()
    }
    lobbyCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  lobbyIsLive.value = false
  const s = Math.floor(diff / 1000)
  lobbyCountdown.value = {
    days: Math.floor(s / 86400),
    hours: Math.floor((s % 86400) / 3600),
    minutes: Math.floor((s % 3600) / 60),
    seconds: s % 60,
  }
}

// Poll draft state once lobby goes live (fallback when WebSocket is unavailable)
function startPolling() {
  if (pollTimer) return
  draft.loadDraft(props.leagueId)
  pollTimer = setInterval(() => {
    draft.loadDraft(props.leagueId)
  }, 4000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

onMounted(() => {
  updateLobbyCountdown()
  lobbyTimer = setInterval(updateLobbyCountdown, 1000)
})

onUnmounted(() => {
  if (lobbyTimer) clearInterval(lobbyTimer)
  stopPolling()
})

function pad(n) {
  return String(n).padStart(2, '0')
}

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function isPresent(member) {
  return draft.presentMembers.some((u) => u.id === member.id)
}
</script>

<template>
  <div class="space-y-4">
    <div class="ds-card p-6 text-center space-y-4">
      <svg class="w-16 h-16 text-ds-primary/40 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>

      <div>
        <h2 class="text-lg font-bold text-ds-text-primary">Draft Lobby</h2>
        <p class="text-sm text-ds-text-secondary mt-1">
          The draft will start automatically at the scheduled time.
        </p>
      </div>

      <!-- Draft is live -->
      <div v-if="nextDraftAt && lobbyIsLive" class="py-1 space-y-3">
        <p class="text-lg font-bold text-ds-green animate-pulse">Draft is starting...</p>
        <p class="text-xs text-ds-text-tertiary">The system is building the pick pool and launching the draft.</p>
      </div>

      <!-- Countdown to draft -->
      <div v-else-if="nextDraftAt" class="py-1">
        <p class="text-xs text-ds-text-tertiary uppercase tracking-wide mb-2">Draft begins in</p>
        <div class="flex justify-center gap-3">
          <div v-if="lobbyCountdown.days > 0" class="text-center">
            <p class="text-2xl font-bold text-ds-text-primary tabular-nums">{{ pad(lobbyCountdown.days) }}</p>
            <p class="text-[10px] text-ds-text-tertiary uppercase">D</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold text-ds-text-primary tabular-nums">{{ pad(lobbyCountdown.hours) }}</p>
            <p class="text-[10px] text-ds-text-tertiary uppercase">H</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold text-ds-text-primary tabular-nums">{{ pad(lobbyCountdown.minutes) }}</p>
            <p class="text-[10px] text-ds-text-tertiary uppercase">M</p>
          </div>
          <div class="text-center">
            <p class="text-2xl font-bold text-ds-text-primary tabular-nums">{{ pad(lobbyCountdown.seconds) }}</p>
            <p class="text-[10px] text-ds-text-tertiary uppercase">S</p>
          </div>
        </div>
      </div>

      <!-- Members in lobby -->
      <div v-if="draft.draftState?.members" class="space-y-2">
        <p class="text-xs text-ds-text-tertiary uppercase tracking-wide">
          In Room ({{ draft.presentMembers.length }}/{{ draft.draftState.members.length }})
        </p>
        <div class="flex flex-wrap justify-center gap-3">
          <div
            v-for="member in draft.draftState.members"
            :key="member.id"
            class="flex flex-col items-center gap-1 transition-opacity duration-300"
            :class="isPresent(member) ? 'opacity-100' : 'opacity-30'"
          >
            <div class="relative">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold border-2 overflow-hidden flex-shrink-0"
                :class="isPresent(member)
                  ? 'border-ds-green bg-ds-bg-hover text-ds-text-secondary'
                  : 'border-ds-border bg-ds-bg-hover text-ds-text-secondary'"
              >
                <img
                  v-if="member.avatar_url"
                  :src="member.avatar_url"
                  :alt="member.team_name"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ initials(member.team_name) }}</span>
              </div>
              <span
                v-if="isPresent(member)"
                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-ds-green rounded-full border-2 border-ds-bg-secondary"
              ></span>
            </div>
            <span class="text-[10px] font-medium text-ds-text-tertiary max-w-[60px] truncate">{{ member.team_name }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
