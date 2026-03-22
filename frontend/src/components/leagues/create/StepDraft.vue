<script setup>
import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const timezones = [
  { value: 'America/New_York', label: 'Eastern (ET)' },
  { value: 'America/Chicago', label: 'Central (CT)' },
  { value: 'America/Denver', label: 'Mountain (MT)' },
  { value: 'America/Los_Angeles', label: 'Pacific (PT)' },
]

const timerOptions = [30, 45, 60, 90, 120]

watch(
  () => [props.modelValue.draft_day, props.modelValue.draft_time, props.modelValue.pick_timer_seconds],
  () => {
    emit('valid', true)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Draft Day</label>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="(day, index) in dayNames"
          :key="index"
          @click="update('draft_day', index)"
          class="px-3 py-1.5 text-xs font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.draft_day === index
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ day.slice(0, 3) }}
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
