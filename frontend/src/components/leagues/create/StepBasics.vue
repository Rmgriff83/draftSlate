<script setup>
import { watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'valid'])

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const teamOptions = [4, 6, 8, 10, 12, 14]

const supportedSports = [
  { key: 'basketball_nba', label: 'NBA' },
  { key: 'americanfootball_nfl', label: 'NFL' },
  { key: 'baseball_mlb', label: 'MLB' },
  { key: 'icehockey_nhl', label: 'NHL' },
]

function toggleSport(key) {
  const current = props.modelValue.sports || []
  const idx = current.indexOf(key)
  if (idx >= 0) {
    // Don't remove if it's the last one
    if (current.length > 1) {
      update('sports', current.filter((s) => s !== key))
    }
  } else {
    update('sports', [...current, key])
  }
}

watch(
  () => [props.modelValue.name, props.modelValue.team_name, props.modelValue.max_teams, props.modelValue.sports],
  ([name, teamName, , sports]) => {
    emit('valid', name?.trim().length > 0 && name.length <= 100 && teamName?.trim().length > 0 && sports?.length > 0)
  },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">League Name</label>
      <input
        :value="modelValue.name"
        @input="update('name', $event.target.value)"
        type="text"
        maxlength="100"
        placeholder="e.g. Sunday Showdown"
        class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
      />
      <p class="text-xs text-ds-text-tertiary mt-1">{{ modelValue.name?.length || 0 }}/100</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-1">Your Team Name</label>
      <input
        :value="modelValue.team_name"
        @input="update('team_name', $event.target.value)"
        type="text"
        maxlength="50"
        placeholder="e.g. The Underdogs"
        class="w-full px-3 py-2 bg-ds-bg-primary border border-ds-border rounded-ds-sm text-ds-text-primary placeholder-ds-text-tertiary focus:outline-none focus:ring-2 focus:ring-ds-primary/50"
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">League Type</label>
      <div class="grid grid-cols-2 gap-3">
        <button
          @click="update('type', 'public')"
          class="ds-card p-3 text-center transition-all duration-ds-fast"
          :class="modelValue.type === 'public' ? 'ring-2 ring-ds-primary' : ''"
        >
          <p class="text-sm font-semibold text-ds-text-primary">Public</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">Anyone can find & join</p>
        </button>
        <button
          @click="update('type', 'private')"
          class="ds-card p-3 text-center transition-all duration-ds-fast"
          :class="modelValue.type === 'private' ? 'ring-2 ring-ds-primary' : ''"
        >
          <p class="text-sm font-semibold text-ds-text-primary">Private</p>
          <p class="text-xs text-ds-text-tertiary mt-0.5">Invite link only</p>
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Max Teams</label>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="n in teamOptions"
          :key="n"
          @click="update('max_teams', n)"
          class="px-4 py-2 text-sm font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.max_teams === n
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ n }}
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-ds-text-primary mb-2">Sports</label>
      <p class="text-xs text-ds-text-tertiary mb-2">Select one or more sports for the league pool</p>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="sport in supportedSports"
          :key="sport.key"
          @click="toggleSport(sport.key)"
          class="px-4 py-2 text-sm font-medium rounded-ds-sm border transition-colors duration-ds-fast"
          :class="modelValue.sports?.includes(sport.key)
            ? 'bg-ds-primary text-white border-ds-primary'
            : 'bg-ds-bg-secondary border-ds-border text-ds-text-secondary hover:border-ds-primary/50'"
        >
          {{ sport.label }}
        </button>
      </div>
    </div>
  </div>
</template>
