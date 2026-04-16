<script setup>
import { ref, onMounted, computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useDraftStore } from '@/stores/draft'
import { useSlateHelpers } from '@/composables/useSlateHelpers'
import { usePlayerHeadshot } from '@/composables/usePlayerHeadshot'
import { useTeamLogo } from '@/composables/useTeamLogo'
import GameLogBarChart from '@/components/draft/GameLogBarChart.vue'
import OddsChart from '@/components/league/OddsChart.vue'
import api from '@/utils/api'

const props = defineProps({
  pick: { type: Object, required: true },
  isMyTurn: { type: Boolean, default: false },
})

const emit = defineEmits(['draft', 'close'])

const draft = useDraftStore()
const {
  sportIcons,
  sportIconColors,
  typeLabels,
  typeBadgeClasses,
  formatOdds,
  oddsColor,
} = useSlateHelpers()

const activeTab = ref('gamelog')
const studyLoading = ref(true)
const studyError = ref('')
const studyData = ref(null)

const { headshotUrl, loadHeadshot } = usePlayerHeadshot(props.pick)
const { homeLogoUrl, awayLogoUrl, loadTeamLogos } = useTeamLogo(props.pick)

const hasUnfilledStarters = computed(() =>
  Object.keys(draft.unfilledTypes).length > 0
)

function isMatchingType(pick) {
  if (!hasUnfilledStarters.value) return true
  return !!draft.unfilledTypes[pick.pick_type]
}

const wouldBustPick = computed(() => {
  if (!hasUnfilledStarters.value) return false
  if (!isMatchingType(props.pick)) return false
  return draft.wouldBustAggregate(props.pick.snapshot_odds)
})

const aggregateImpact = computed(() => {
  if (!hasUnfilledStarters.value) return null
  if (!isMatchingType(props.pick)) return null
  const newAvg = draft.calcNewAggregate(props.pick.snapshot_odds)
  return draft.probToAmerican(newAvg)
})

async function fetchStudyData() {
  studyLoading.value = true
  studyError.value = ''
  try {
    const { data } = await api.get(`/api/v1/picks/${props.pick.id}/study`)
    studyData.value = data.study
  } catch (err) {
    studyError.value = 'Failed to load study data'
    console.error('Study fetch error:', err)
  } finally {
    studyLoading.value = false
  }
}

onMounted(() => {
  fetchStudyData()
  loadHeadshot()
  loadTeamLogos()
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
      <div class="ds-card w-full max-w-lg rounded-b-none relative z-10 animate-slide-up max-h-[80vh] overflow-y-auto">
        <!-- Drag handle -->
        <div class="sticky top-0 bg-ds-bg-secondary z-10 px-5 pt-3 pb-2 rounded-t-ds-lg">
          <div class="w-10 h-1 bg-ds-border rounded-full mx-auto mb-3"></div>

          <!-- Header -->
          <div class="flex items-center gap-2 mb-3">
            <Icon
              :icon="sportIcons[pick.sport] || 'mdi:trophy'"
              :class="['w-6 h-6', sportIconColors[pick.sport] || 'text-ds-text-tertiary']"
            />
            <h3 class="text-lg font-bold text-ds-text-primary flex-1">Pick Study</h3>
            <button @click="emit('close')" class="text-ds-text-tertiary hover:text-ds-text-primary p-1">
              <Icon icon="mdi:close" class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div class="px-5 pb-5">
          <!-- Pick Info Card -->
          <div class="ds-card bg-ds-bg-hover p-4 mb-4">
            <div class="flex gap-3">
              <!-- Player headshot -->
              <div v-if="pick.pick_type === 'player_prop'" class="flex-shrink-0">
                <img
                  v-if="headshotUrl"
                  :src="headshotUrl"
                  :alt="pick.player_name"
                  class="w-12 h-12 rounded-full object-cover bg-ds-bg-primary"
                  @error="headshotUrl = null"
                />
                <div v-else class="w-12 h-12 rounded-full bg-ds-bg-primary flex items-center justify-center">
                  <Icon icon="mdi:account" class="w-7 h-7 text-ds-text-tertiary" />
                </div>
              </div>

              <!-- Team logos for game-level picks -->
              <div v-else class="flex-shrink-0 flex items-center gap-1">
                <img
                  v-if="awayLogoUrl"
                  :src="awayLogoUrl"
                  :alt="pick.away_team"
                  class="w-8 h-8 object-contain"
                  @error="awayLogoUrl = null"
                />
                <div v-else class="w-8 h-8 rounded bg-ds-bg-primary flex items-center justify-center">
                  <Icon icon="mdi:shield-outline" class="w-5 h-5 text-ds-text-tertiary" />
                </div>
                <span class="text-[10px] text-ds-text-tertiary font-bold">vs</span>
                <img
                  v-if="homeLogoUrl"
                  :src="homeLogoUrl"
                  :alt="pick.home_team"
                  class="w-8 h-8 object-contain"
                  @error="homeLogoUrl = null"
                />
                <div v-else class="w-8 h-8 rounded bg-ds-bg-primary flex items-center justify-center">
                  <Icon icon="mdi:shield-outline" class="w-5 h-5 text-ds-text-tertiary" />
                </div>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span
                    :class="['text-xs font-bold px-2 py-0.5 rounded', typeBadgeClasses[pick.pick_type] || 'bg-ds-bg-hover text-ds-text-secondary']"
                  >
                    {{ typeLabels[pick.pick_type] || pick.pick_type }}
                  </span>
                  <span v-if="pick.category" class="text-xs text-ds-text-tertiary">{{ pick.category }}</span>
                </div>

                <p class="text-base font-semibold text-ds-text-primary mb-1">{{ pick.description }}</p>
                <p class="text-sm text-ds-text-tertiary">{{ pick.game_display }}</p>

                <div class="flex items-center gap-4 mt-3">
                  <div>
                    <p class="text-[10px] text-ds-text-tertiary uppercase">Snapshot</p>
                    <p :class="['text-sm font-mono font-bold', oddsColor(pick.snapshot_odds)]">
                      {{ formatOdds(pick.snapshot_odds) }}
                    </p>
                  </div>
                  <div v-if="pick.current_odds && pick.current_odds !== pick.snapshot_odds">
                    <p class="text-[10px] text-ds-text-tertiary uppercase">Current</p>
                    <p :class="['text-sm font-mono font-bold', oddsColor(pick.current_odds)]">
                      {{ formatOdds(pick.current_odds) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sub-tab bar -->
          <div class="flex border-b border-ds-border mb-4">
            <button
              @click="activeTab = 'gamelog'"
              :class="[
                'flex-1 text-sm font-semibold py-2.5 text-center transition-colors border-b-2',
                activeTab === 'gamelog'
                  ? 'border-ds-primary text-ds-primary'
                  : 'border-transparent text-ds-text-tertiary hover:text-ds-text-secondary'
              ]"
            >
              Game Log
            </button>
            <button
              @click="activeTab = 'movement'"
              :class="[
                'flex-1 text-sm font-semibold py-2.5 text-center transition-colors border-b-2',
                activeTab === 'movement'
                  ? 'border-ds-primary text-ds-primary'
                  : 'border-transparent text-ds-text-tertiary hover:text-ds-text-secondary'
              ]"
            >
              Line Movement
            </button>
          </div>

          <!-- Tab content -->
          <div class="min-h-[200px]">
            <!-- Game Log Tab -->
            <template v-if="activeTab === 'gamelog'">
              <!-- Loading -->
              <div v-if="studyLoading" class="flex items-center justify-center h-[200px]">
                <div class="w-5 h-5 border-2 border-ds-primary border-t-transparent rounded-full animate-spin"></div>
              </div>

              <!-- Error -->
              <div v-else-if="studyError" class="flex items-center justify-center h-[200px]">
                <p class="text-xs text-ds-red">{{ studyError }}</p>
              </div>

              <!-- Stats available: NBA player prop -->
              <template v-else-if="studyData?.stats_available">
                <!-- Hit rate banner -->
                <div class="ds-card bg-ds-bg-hover p-3 mb-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <span class="text-2xl font-black text-ds-text-primary">{{ studyData.hit_count }}/{{ studyData.games_count }}</span>
                      <span class="text-sm text-ds-text-tertiary ml-2">
                        {{ studyData.side?.toLowerCase() }} {{ studyData.threshold }}
                      </span>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-bold text-ds-text-primary">{{ studyData.average }}</p>
                      <p class="text-[10px] text-ds-text-tertiary uppercase">avg {{ studyData.stat_label }}</p>
                    </div>
                  </div>

                  <!-- Hit rate bar -->
                  <div class="mt-2 h-1.5 bg-ds-border rounded-full overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="studyData.hit_count / studyData.games_count >= 0.6 ? 'bg-green-500' : studyData.hit_count / studyData.games_count >= 0.4 ? 'bg-yellow-500' : 'bg-red-500'"
                      :style="{ width: `${(studyData.hit_count / studyData.games_count) * 100}%` }"
                    ></div>
                  </div>
                </div>

                <!-- Bar chart -->
                <GameLogBarChart
                  :games="studyData.games"
                  :threshold="studyData.threshold"
                  :stat-label="studyData.stat_label"
                  class="mb-3"
                />

                <!-- Game-by-game table -->
                <div class="ds-card bg-ds-bg-hover overflow-hidden">
                  <table class="w-full text-xs">
                    <thead>
                      <tr class="border-b border-ds-border">
                        <th class="text-left text-ds-text-tertiary font-medium px-3 py-2">Date</th>
                        <th class="text-left text-ds-text-tertiary font-medium px-3 py-2">OPP</th>
                        <th class="text-right text-ds-text-tertiary font-medium px-3 py-2">{{ studyData.stat_label }}</th>
                        <th class="text-right text-ds-text-tertiary font-medium px-3 py-2">W/L</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(game, i) in studyData.games"
                        :key="i"
                        class="border-b border-ds-border/30 last:border-0"
                      >
                        <td class="px-3 py-2 text-ds-text-tertiary">{{ game.date }}</td>
                        <td class="px-3 py-2 text-ds-text-secondary font-medium">{{ game.opponent }}</td>
                        <td
                          class="px-3 py-2 text-right font-bold font-mono"
                          :class="game.hit ? 'text-green-400' : 'text-red-400'"
                        >
                          {{ game.stat_value }}
                        </td>
                        <td class="px-3 py-2 text-right text-ds-text-tertiary">{{ game.result }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <!-- Stats not available -->
              <div v-else class="flex flex-col items-center justify-center h-[200px] text-center">
                <Icon icon="mdi:chart-bar-stacked" class="w-10 h-10 text-ds-text-tertiary mb-2" />
                <p class="text-sm text-ds-text-secondary">Game log stats not available</p>
                <p class="text-xs text-ds-text-tertiary mt-1">for this pick type</p>
              </div>
            </template>

            <!-- Line Movement Tab -->
            <template v-else-if="activeTab === 'movement'">
              <OddsChart :pick-selection-id="pick.id" />
            </template>
          </div>

          <!-- Aggregate impact warning -->
          <div
            v-if="isMyTurn && wouldBustPick"
            class="ds-card bg-red-500/10 border border-red-500/30 p-3 mt-4 flex items-center gap-2"
          >
            <Icon icon="mdi:alert" class="w-4 h-4 text-red-400 flex-shrink-0" />
            <p class="text-xs text-red-400">
              This pick would push your aggregate odds past the floor ({{ formatOdds(draft.aggregateOddsFloor) }}).
            </p>
          </div>

          <!-- Aggregate impact preview (non-busting) -->
          <p
            v-else-if="isMyTurn && aggregateImpact !== null && hasUnfilledStarters && isMatchingType(pick)"
            class="text-[10px] text-ds-text-tertiary mt-3 text-center"
          >
            Avg after: {{ formatOdds(aggregateImpact) }}
          </p>

          <!-- Draft action button -->
          <div class="mt-4">
            <button
              v-if="isMyTurn"
              @click="emit('draft')"
              :disabled="draft.loading || wouldBustPick"
              class="w-full px-4 py-3 text-sm font-semibold text-white rounded-ds-sm transition-colors"
              :class="wouldBustPick
                ? 'bg-ds-bg-hover cursor-not-allowed opacity-50'
                : 'bg-ds-green hover:bg-green-600'"
            >
              {{ draft.loading ? 'Picking...' : 'Draft This Pick' }}
            </button>
            <p v-else class="text-center text-sm text-ds-text-tertiary py-2">
              Waiting for your turn
            </p>
          </div>
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
