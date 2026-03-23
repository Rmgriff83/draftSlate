<script setup>
defineProps({
  pick: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

defineEmits(['confirm', 'close'])

function formatOdds(odds) {
  return odds > 0 ? `+${odds}` : `${odds}`
}

function formatGameTime(gameTime) {
  if (!gameTime) return null
  const d = new Date(gameTime)
  const now = new Date()
  const isToday = d.toDateString() === now.toDateString()
  const tomorrow = new Date(now)
  tomorrow.setDate(tomorrow.getDate() + 1)
  const isTomorrow = d.toDateString() === tomorrow.toDateString()
  const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
  if (isToday) return `Today ${time}`
  if (isTomorrow) return `Tomorrow ${time}`
  const month = d.toLocaleDateString([], { month: 'short', day: 'numeric' })
  return `${month} ${time}`
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>
      <div class="ds-card w-full max-w-lg rounded-b-none p-5 relative z-10 animate-slide-up">
        <div class="w-10 h-1 bg-ds-border rounded-full mx-auto mb-4"></div>

        <h3 class="text-lg font-bold text-ds-text-primary mb-1">Confirm Pick</h3>

        <div class="ds-card bg-ds-bg-hover p-4 my-3">
          <p class="text-sm font-semibold text-ds-text-primary">{{ pick.description }}</p>
          <div class="flex items-center gap-3 mt-2">
            <span class="text-xs text-ds-text-tertiary">{{ pick.game_display }}</span>
            <span class="text-sm font-mono font-bold text-ds-primary">{{ formatOdds(pick.snapshot_odds) }}</span>
          </div>
          <p v-if="formatGameTime(pick.game_time)" class="text-xs text-ds-text-tertiary mt-1">
            {{ formatGameTime(pick.game_time) }}
          </p>
          <p v-if="pick.pick_type" class="text-xs text-ds-text-tertiary mt-1">
            Type: {{ pick.pick_type }} {{ pick.category ? `(${pick.category})` : '' }}
          </p>
        </div>

        <p v-if="error" class="text-xs text-ds-red mb-3">{{ error }}</p>

        <div class="flex gap-3">
          <button
            @click="$emit('close')"
            class="flex-1 px-4 py-2.5 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors"
          >
            Cancel
          </button>
          <button
            @click="$emit('confirm')"
            :disabled="loading"
            class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors disabled:opacity-50"
          >
            {{ loading ? 'Picking...' : 'Confirm Pick' }}
          </button>
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
