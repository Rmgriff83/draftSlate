<script setup>
import { ref, computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useSlateStore } from '@/stores/slate'
import { useSlateHelpers } from '@/composables/useSlateHelpers'

const props = defineProps({
  pick: { type: Object, required: true },
})

const emit = defineEmits(['close', 'swap'])

const slate = useSlateStore()

const {
  sportIcons,
  sportIconColors,
  typeLabels,
  typeBadgeClasses,
  outcomeBadgeClasses,
  outcomeLabel,
  formatOdds,
  oddsColor,
  formatDrift,
  driftColor,
  isLive,
} = useSlateHelpers()

const showMoveMenu = ref(false)

const ps = computed(() => props.pick.pick_selection || {})
const outcome = computed(() => ps.value.outcome || 'pending')
const isPending = computed(() => outcome.value === 'pending')
const isLocked = computed(() => props.pick.is_locked)
const isStarter = computed(() => props.pick.position === 'starter')
const gameStarted = computed(() => {
  const gt = ps.value.game_time
  if (!gt) return false
  return new Date(gt) <= new Date()
})
const canSwap = computed(() => !isLocked.value && !gameStarted.value)
const pickType = computed(() => ps.value.pick_type || props.pick.slot_type)

function pickGameStarted(pick) {
  const gt = pick?.pick_selection?.game_time
  if (!gt) return false
  return new Date(gt) <= new Date()
}

const draftedOdds = computed(() => props.pick.drafted_odds)
const displayOdds = computed(() =>
  isLocked.value ? props.pick.locked_odds : (ps.value.current_odds ?? ps.value.snapshot_odds)
)
const drift = computed(() => props.pick.odds_drift)

// Build the list of valid move targets based on the pick's type
const moveTargets = computed(() => {
  const targets = []
  const allPicks = slate.myPicks
  const type = pickType.value
  const currentPickId = props.pick.id

  if (isStarter.value) {
    // Starter can swap with same-type starters or move to bench

    // Other same-type starters (swap positions)
    const sameTypeStarters = allPicks.filter(
      (p) => p.position === 'starter' && p.slot_type === type && p.id !== currentPickId && !p.is_locked && !pickGameStarted(p)
    )
    for (const p of sameTypeStarters) {
      targets.push({
        label: `Swap with ${typeLabels[type]} Starter ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'starter',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }

    // Bench — find an open bench slot, or swap with same-type bench picks
    const sameTypeBench = allPicks.filter(
      (p) => p.position === 'bench' && p.slot_type === type && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeBench) {
      targets.push({
        label: `Swap with Bench ${typeLabels[p.slot_type]} ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'bench',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }

    // Move to new bench slot (next available number)
    const benchSlotsOfType = allPicks
      .filter((p) => p.position === 'bench' && p.slot_type === type)
      .map((p) => p.slot_number)
    const nextBenchSlot = benchSlotsOfType.length > 0 ? Math.max(...benchSlotsOfType) + 1 : 1

    targets.push({
      label: 'Move to Bench',
      sublabel: `Open ${typeLabels[type]} bench slot`,
      position: 'bench',
      slotNumber: nextBenchSlot,
      slotType: type,
      icon: 'mdi:arrow-down',
    })
  } else {
    // Bench pick can promote to starter (swap with same-type starter) or swap with other bench

    // Same-type starter slots to swap into
    const sameTypeStarters = allPicks.filter(
      (p) => p.position === 'starter' && p.slot_type === type && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeStarters) {
      targets.push({
        label: `Promote to ${typeLabels[type]} Starter ${p.slot_number}`,
        sublabel: `Swap with: ${p.pick_selection?.description}`,
        position: 'starter',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:arrow-up',
        primary: true,
      })
    }

    // Other same-type bench picks to swap with
    const sameTypeBench = allPicks.filter(
      (p) => p.position === 'bench' && p.slot_type === type && p.id !== currentPickId && !p.is_locked && !pickGameStarted(p)
    )

    for (const p of sameTypeBench) {
      targets.push({
        label: `Swap with Bench ${typeLabels[p.slot_type]} ${p.slot_number}`,
        sublabel: p.pick_selection?.description,
        position: 'bench',
        slotNumber: p.slot_number,
        slotType: type,
        icon: 'mdi:swap-horizontal',
      })
    }
  }

  return targets
})

function handleMove(target) {
  emit('swap', {
    pickId: props.pick.id,
    targetPosition: target.position,
    targetSlot: target.slotNumber,
    targetSlotType: target.slotType,
  })
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
      <div class="ds-card w-full max-w-lg rounded-b-none p-5 relative z-10 animate-slide-up max-h-[80vh] overflow-y-auto">
        <!-- Drag handle -->
        <div class="w-10 h-1 bg-ds-border rounded-full mx-auto mb-4"></div>

        <!-- Header -->
        <div class="flex items-center gap-2 mb-3">
          <Icon
            :icon="sportIcons[ps.sport] || 'mdi:trophy'"
            :class="['w-6 h-6', sportIconColors[ps.sport] || 'text-gray-400']"
          />
          <h3 class="text-lg font-bold text-ds-text-primary flex-1">Pick Details</h3>
          <button @click="emit('close')" class="text-gray-400 hover:text-white">
            <Icon icon="mdi:close" class="w-5 h-5" />
          </button>
        </div>

        <!-- Pick info card -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <div class="flex items-center gap-2 mb-2">
            <span
              :class="['text-xs font-bold px-2 py-0.5 rounded', typeBadgeClasses[ps.pick_type] || 'bg-gray-600 text-gray-300']"
            >
              {{ typeLabels[ps.pick_type] || ps.pick_type }}
            </span>
            <span v-if="ps.category" class="text-xs text-gray-400">{{ ps.category }}</span>
            <span class="text-[10px] text-gray-500 ml-auto">
              {{ isStarter ? `Starter ${pick.slot_number}` : `Bench ${pick.slot_number}` }}
            </span>
          </div>

          <p class="text-base font-semibold text-white mb-1">{{ ps.description }}</p>
          <p class="text-sm text-gray-400">{{ ps.game_display }}</p>

          <div v-if="ps.player_name" class="mt-2">
            <span class="text-xs text-gray-500">Player:</span>
            <span class="text-sm text-gray-300 ml-1">{{ ps.player_name }}</span>
          </div>
        </div>

        <!-- Odds section -->
        <div class="ds-card bg-ds-bg-hover p-4 mb-4">
          <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Odds</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Drafted</p>
              <p :class="['text-lg font-bold', oddsColor(draftedOdds)]">
                {{ formatOdds(draftedOdds) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">
                {{ isLocked ? 'Locked' : 'Current' }}
              </p>
              <p :class="['text-lg font-bold', oddsColor(displayOdds)]">
                {{ formatOdds(displayOdds) }}
              </p>
            </div>
          </div>

          <!-- Drift indicator -->
          <div v-if="isLocked && drift" class="mt-2 pt-2 border-t border-gray-700">
            <span class="text-xs text-gray-500">Drift:</span>
            <span :class="['text-sm font-semibold ml-1', driftColor(drift)]">
              {{ formatDrift(drift) }}
            </span>
          </div>
        </div>

        <!-- Outcome badge (if graded) -->
        <div v-if="!isPending" class="mb-4">
          <div
            :class="[
              'py-4 rounded-lg text-center',
              outcome === 'hit' ? 'bg-green-500/10 border border-green-500/30' : '',
              outcome === 'miss' ? 'bg-red-500/10 border border-red-500/30' : '',
              outcome === 'void' ? 'bg-gray-500/10 border border-gray-500/30' : '',
              outcome === 'push' ? 'bg-yellow-500/10 border border-yellow-500/30' : '',
            ]"
          >
            <span :class="['text-2xl font-black tracking-wider', outcomeBadgeClasses[outcome]]">
              {{ outcomeLabel(outcome) }}
            </span>
          </div>
        </div>

        <!-- Lock indicator -->
        <div v-if="(isLocked || gameStarted) && isPending" class="ds-card bg-ds-bg-hover p-3 mb-4 flex items-center gap-2">
          <Icon icon="mdi:lock" class="w-4 h-4 text-yellow-500" />
          <span class="text-sm text-yellow-400">
            {{ gameStarted && !isLocked ? 'Game in progress — pick locked' : 'Pick locked at game start' }}
          </span>
          <span v-if="pick.locked_at" class="text-xs text-gray-500 ml-auto">
            {{ new Date(pick.locked_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
          </span>
        </div>

        <!-- Move button / menu (only if not locked) -->
        <template v-if="canSwap">
          <!-- Toggle button -->
          <button
            v-if="!showMoveMenu"
            @click="showMoveMenu = true"
            class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors flex items-center justify-center gap-2"
          >
            <Icon icon="mdi:swap-vertical" class="w-4 h-4" />
            Move Pick
          </button>

          <!-- Contextual move targets -->
          <div v-else class="space-y-2">
            <div class="flex items-center justify-between mb-1">
              <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Move to</h4>
              <button @click="showMoveMenu = false" class="text-xs text-ds-primary hover:underline">
                Cancel
              </button>
            </div>

            <button
              v-for="(target, i) in moveTargets"
              :key="i"
              @click="handleMove(target)"
              :class="[
                'w-full flex items-center gap-3 p-3 rounded-lg text-left transition-colors',
                target.primary
                  ? 'bg-ds-primary/10 border border-ds-primary/30 hover:bg-ds-primary/20'
                  : 'bg-ds-bg-hover hover:bg-ds-border',
              ]"
            >
              <Icon
                :icon="target.icon"
                :class="['w-5 h-5 flex-shrink-0', target.primary ? 'text-ds-primary' : 'text-gray-400']"
              />
              <div class="flex-1 min-w-0">
                <p :class="['text-sm font-medium', target.primary ? 'text-ds-primary' : 'text-white']">
                  {{ target.label }}
                </p>
                <p v-if="target.sublabel" class="text-xs text-gray-500 truncate">{{ target.sublabel }}</p>
              </div>
              <Icon icon="mdi:chevron-right" class="w-4 h-4 text-gray-600 flex-shrink-0" />
            </button>

            <p v-if="moveTargets.length === 0" class="text-sm text-gray-500 text-center py-2">
              No valid move targets for this pick type.
            </p>
          </div>
        </template>
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
