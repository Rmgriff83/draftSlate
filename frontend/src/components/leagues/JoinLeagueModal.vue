<script setup>
import { ref } from 'vue'

const props = defineProps({
  league: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'close'])

const teamName = ref('')
const error = ref('')

function submit() {
  if (!teamName.value.trim()) {
    error.value = 'Team name is required.'
    return
  }
  if (teamName.value.length > 50) {
    error.value = 'Team name must be 50 characters or less.'
    return
  }
  error.value = ''
  emit('confirm', teamName.value.trim())
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')"></div>
      <div class="ds-card p-6 w-full max-w-sm relative z-10">
        <h2 class="text-lg font-bold text-ds-text-primary mb-1">Join {{ league.name }}</h2>
        <p class="text-sm text-ds-text-secondary mb-4">Buy-in: ${{ league.buy_in }}</p>

        <div class="mb-4">
          <label class="block text-sm font-medium text-ds-text-primary mb-1">Your Team Name</label>
          <input
            v-model="teamName"
            type="text"
            maxlength="50"
            placeholder="Enter a team name"
            class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
            @keyup.enter="submit"
          />
          <p v-if="error" class="text-xs text-ds-red mt-1">{{ error }}</p>
        </div>

        <div class="flex gap-3">
          <button
            @click="$emit('close')"
            class="flex-1 px-4 py-2 text-sm font-medium text-ds-text-secondary bg-ds-bg-hover rounded-ds-sm hover:bg-ds-border transition-colors duration-ds-fast"
          >
            Cancel
          </button>
          <button
            @click="submit"
            :disabled="loading"
            class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-ds-primary hover:bg-ds-primary-light rounded-ds-sm transition-colors duration-ds-fast disabled:opacity-50"
          >
            {{ loading ? 'Joining...' : 'Confirm & Join' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
