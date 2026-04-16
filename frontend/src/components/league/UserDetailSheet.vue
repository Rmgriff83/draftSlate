<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'

const props = defineProps({
  member: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const careerLoading = ref(true)
const careerError = ref('')
const career = ref(null)

async function fetchCareerStats() {
  careerLoading.value = true
  careerError.value = ''
  try {
    const { data } = await api.get(`/api/v1/users/${props.member.user_id}/career-stats`)
    career.value = data
  } catch {
    careerError.value = 'Failed to load career stats'
  } finally {
    careerLoading.value = false
  }
}

onMounted(fetchCareerStats)

const record = computed(() => {
  const m = props.member
  const parts = [m.wins, m.losses]
  if (m.ties > 0) parts.push(m.ties)
  return parts.join('-')
})

const winPct = computed(() => {
  const m = props.member
  const total = m.wins + m.losses + (m.ties || 0)
  if (total === 0) return '.000'
  return (m.wins / total).toFixed(3).replace(/^0/, '')
})

const hasMedals = computed(() => {
  if (!career.value) return false
  return (career.value.career_gold_medals || 0) + (career.value.career_silver_medals || 0) + (career.value.career_bronze_medals || 0) > 0
})

const leagueMedal = computed(() => {
  const pos = props.member.final_position
  if (pos === 1) return { label: '1st', cls: 'text-yellow-400 bg-yellow-400/10' }
  if (pos === 2) return { label: '2nd', cls: 'text-gray-300 bg-gray-300/10' }
  if (pos === 3) return { label: '3rd', cls: 'text-amber-600 bg-amber-600/10' }
  return null
})

const hitRate = computed(() => {
  if (!career.value || career.value.career_picks_graded === 0) return 0
  return career.value.career_picks_hit / career.value.career_picks_graded
})

const hitPct = computed(() => {
  if (!career.value || career.value.career_picks_graded === 0) return '0%'
  return (hitRate.value * 100).toFixed(1) + '%'
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
      <div class="ds-card w-full max-w-lg rounded-b-none p-5 relative z-10 animate-slide-up max-h-[80vh] overflow-y-auto">
        <!-- Drag handle -->
        <div class="w-10 h-1 bg-ds-border rounded-full mx-auto mb-4"></div>

        <!-- Header -->
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-ds-primary/20 flex items-center justify-center flex-shrink-0">
            <span class="text-sm font-bold text-ds-primary">
              {{ (member.team_name || '?')[0].toUpperCase() }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <p class="text-base font-bold text-ds-text-primary truncate">{{ member.team_name }}</p>
              <span
                v-if="leagueMedal"
                :class="['text-[10px] font-bold px-1.5 py-0.5 rounded flex-shrink-0', leagueMedal.cls]"
              >
                {{ leagueMedal.label }}
              </span>
            </div>
            <p class="text-xs text-ds-text-tertiary truncate">{{ member.user_name }}</p>
          </div>
          <button @click="emit('close')" class="text-gray-400 hover:text-ds-text-primary flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- League Record -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">League Record</h4>
          <div class="grid grid-cols-4 gap-3 text-center">
            <div>
              <p class="text-lg font-bold text-ds-text-primary font-mono">{{ record }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Record</p>
            </div>
            <div>
              <p class="text-lg font-bold text-ds-text-primary font-mono">{{ winPct }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Win %</p>
            </div>
            <div>
              <p class="text-lg font-bold text-ds-text-primary font-mono">{{ member.rank || '—' }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Rank</p>
            </div>
            <div>
              <p class="text-lg font-bold text-ds-text-primary font-mono">{{ member.playoff_seed || '—' }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Seed</p>
            </div>
          </div>
        </div>

        <!-- League Picks -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">League Correct Picks</span>
            <span class="text-lg font-bold text-ds-text-primary font-mono">{{ member.total_correct_picks ?? 0 }}</span>
          </div>
        </div>

        <!-- Career Stats (lazy-loaded) -->
        <div class="ds-card bg-ds-bg-hover p-4">
          <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Career Stats</h4>

          <!-- Loading -->
          <div v-if="careerLoading" class="flex items-center justify-center py-6">
            <div class="w-5 h-5 border-2 border-ds-primary border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Error -->
          <div v-else-if="careerError" class="text-center py-4">
            <p class="text-xs text-ds-red">{{ careerError }}</p>
          </div>

          <!-- Career data -->
          <template v-else-if="career">
            <!-- Hit rate -->
            <div class="mb-4">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm text-ds-text-secondary">
                  <span class="text-lg font-black text-ds-text-primary">{{ career.career_picks_hit }}</span>
                  <span class="text-gray-500">/{{ career.career_picks_graded }}</span>
                </span>
                <span class="text-sm font-bold text-ds-text-primary">{{ hitPct }}</span>
              </div>
              <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="hitRate >= 0.6 ? 'bg-green-500' : hitRate >= 0.4 ? 'bg-yellow-500' : 'bg-red-500'"
                  :style="{ width: `${hitRate * 100}%` }"
                ></div>
              </div>
            </div>

            <!-- Medals -->
            <div v-if="hasMedals" class="flex items-center justify-center gap-4 mb-4 pt-3 border-t border-gray-700/50">
              <div v-if="career.career_gold_medals > 0" class="flex items-center gap-1">
                <span class="text-yellow-400 text-sm font-bold">1st</span>
                <span class="text-sm font-bold text-ds-text-primary">{{ career.career_gold_medals }}</span>
              </div>
              <div v-if="career.career_silver_medals > 0" class="flex items-center gap-1">
                <span class="text-gray-300 text-sm font-bold">2nd</span>
                <span class="text-sm font-bold text-ds-text-primary">{{ career.career_silver_medals }}</span>
              </div>
              <div v-if="career.career_bronze_medals > 0" class="flex items-center gap-1">
                <span class="text-amber-600 text-sm font-bold">3rd</span>
                <span class="text-sm font-bold text-ds-text-primary">{{ career.career_bronze_medals }}</span>
              </div>
            </div>

            <!-- Type breakdown -->
            <div class="grid grid-cols-4 gap-3 text-center">
              <div>
                <p class="text-lg font-bold text-ds-text-primary font-mono">{{ career.career_moneyline_hits }}</p>
                <p class="text-[10px] text-gray-500 uppercase">ML</p>
              </div>
              <div>
                <p class="text-lg font-bold text-ds-text-primary font-mono">{{ career.career_spread_hits }}</p>
                <p class="text-[10px] text-gray-500 uppercase">Spread</p>
              </div>
              <div>
                <p class="text-lg font-bold text-ds-text-primary font-mono">{{ career.career_total_hits }}</p>
                <p class="text-[10px] text-gray-500 uppercase">Total</p>
              </div>
              <div>
                <p class="text-lg font-bold text-ds-text-primary font-mono">{{ career.career_player_prop_hits }}</p>
                <p class="text-[10px] text-gray-500 uppercase">Prop</p>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes slide-up {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
.animate-slide-up {
  animation: slide-up 0.25s ease-out;
}
</style>
