<script setup>
import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const timezones = [
  { value: 'America/New_York', label: 'Eastern (ET)' },
  { value: 'America/Chicago', label: 'Central (CT)' },
  { value: 'America/Denver', label: 'Mountain (MT)' },
  { value: 'America/Los_Angeles', label: 'Pacific (PT)' },
]

const timerOptions = [30, 45, 60, 90, 120]
const durationOptions = [1, 2, 3, 4, 5, 6, 7]
const cutoffOptions = [1, 2, 3, 6, 12, 24, 48]

watch(
  () => [props.modelValue.matchup_duration_days, props.modelValue.draft_time, props.modelValue.pick_timer_seconds, props.modelValue.min_hours_before_game],
  () => {
    emit('valid', true)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Matchup Duration</label>
      <p class="text-xs text-ds-text-tertiary mb-2">Drafts recur every N days at the time below</p>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="days in durationOptions"
          :key="days"
          @click="update('matchup_duration_days', days)"
          class="px-3 py-1.5 text-xs font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.matchup_duration_days === days
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ days }}d
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Event Time Cutoff</label>
      <p class="text-xs text-ds-text-tertiary mb-2">Exclude events starting within this many hours</p>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="hours in cutoffOptions"
          :key="hours"
          @click="update('min_hours_before_game', hours)"
          class="px-3 py-1.5 text-xs font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.min_hours_before_game === hours
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ hours }}h
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Draft Time</label>
      <input
        :value="modelValue.draft_time?.slice(0, 5)"
        @input="update('draft_time', $event.target.value + ':00')"
        type="time"
        class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Timezone</label>
      <select
        :value="modelValue.draft_timezone"
        @change="update('draft_timezone', $event.target.value)"
        class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
      >
        <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Pick Timer</label>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="seconds in timerOptions"
          :key="seconds"
          @click="update('pick_timer_seconds', seconds)"
          class="px-3 py-1.5 text-xs font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.pick_timer_seconds === seconds
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ seconds }}s
        </button>
      </div>
    </div>
  </div>
</template>
