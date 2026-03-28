<script setup>
import { ref, computed, onMounted } from 'vue'
import { Icon } from '@iconify/vue'
import { usePlayerHeadshot } from '@/composables/usePlayerHeadshot'
import { useTeamLogo } from '@/composables/useTeamLogo'

const props = defineProps({
  pick: { type: Object, required: true },
  size: { type: String, default: 'md' },
})

const sportIcons = {
  basketball_nba: 'mdi:basketball',
  americanfootball_nfl: 'mdi:football',
  baseball_mlb: 'mdi:baseball',
  icehockey_nhl: 'mdi:hockey-puck',
}

const sportIconColors = {
  basketball_nba: 'text-orange-400',
  americanfootball_nfl: 'text-amber-600',
  baseball_mlb: 'text-red-400',
  icehockey_nhl: 'text-sky-400',
}

const sizeMap = { sm: 24, md: 32, lg: 40 }
const dualLogoSizeMap = { sm: 14, md: 18, lg: 22 }
const iconSizeMap = { sm: 'w-3.5 h-3.5', md: 'w-5 h-5', lg: 'w-6 h-6' }

const imgFailed = ref(false)
const awayImgFailed = ref(false)
const homeImgFailed = ref(false)

const isPlayerProp = props.pick.pick_type === 'player_prop'
const isTotal = props.pick.pick_type === 'total'
const { headshotUrl, loadHeadshot } = usePlayerHeadshot(props.pick)
const { homeLogoUrl, awayLogoUrl, loadTeamLogos } = useTeamLogo(props.pick)

const pickedTeamLogo = computed(() => {
  const desc = props.pick.description || ''
  if (props.pick.home_team && desc.startsWith(props.pick.home_team)) {
    return homeLogoUrl.value || awayLogoUrl.value
  }
  return awayLogoUrl.value || homeLogoUrl.value
})

const imageUrl = computed(() => isPlayerProp ? headshotUrl.value : pickedTeamLogo.value)

onMounted(() => {
  if (isPlayerProp) {
    loadHeadshot()
  } else {
    loadTeamLogos()
  }
})

function onImgError() {
  imgFailed.value = true
}
</script>

<template>
  <!-- Dual logo for totals (Over/Under) -->
  <div
    v-if="isTotal"
    class="flex-shrink-0 flex items-center justify-center gap-px"
    :style="{ width: `${sizeMap[size]}px`, height: `${sizeMap[size]}px` }"
  >
    <img
      v-if="awayLogoUrl && !awayImgFailed"
      :src="awayLogoUrl"
      :alt="pick.away_team || ''"
      loading="lazy"
      class="object-contain rounded-sm"
      :style="{ width: `${dualLogoSizeMap[size]}px`, height: `${dualLogoSizeMap[size]}px` }"
      @error="awayImgFailed = true"
    />
    <div v-else class="bg-ds-bg-secondary rounded-sm flex items-center justify-center" :style="{ width: `${dualLogoSizeMap[size]}px`, height: `${dualLogoSizeMap[size]}px` }">
      <Icon :icon="sportIcons[pick.sport] || 'mdi:trophy'" class="w-2.5 h-2.5" :class="sportIconColors[pick.sport] || 'text-gray-400'" />
    </div>
    <img
      v-if="homeLogoUrl && !homeImgFailed"
      :src="homeLogoUrl"
      :alt="pick.home_team || ''"
      loading="lazy"
      class="object-contain rounded-sm"
      :style="{ width: `${dualLogoSizeMap[size]}px`, height: `${dualLogoSizeMap[size]}px` }"
      @error="homeImgFailed = true"
    />
    <div v-else class="bg-ds-bg-secondary rounded-sm flex items-center justify-center" :style="{ width: `${dualLogoSizeMap[size]}px`, height: `${dualLogoSizeMap[size]}px` }">
      <Icon :icon="sportIcons[pick.sport] || 'mdi:trophy'" class="w-2.5 h-2.5" :class="sportIconColors[pick.sport] || 'text-gray-400'" />
    </div>
  </div>

  <!-- Single image: player headshot or picked team logo -->
  <div
    v-else
    class="flex-shrink-0 flex items-center justify-center overflow-hidden bg-ds-bg-secondary"
    :class="isPlayerProp ? 'rounded-full' : 'rounded'"
    :style="{ width: `${sizeMap[size]}px`, height: `${sizeMap[size]}px` }"
  >
    <img
      v-if="imageUrl && !imgFailed"
      :src="imageUrl"
      :alt="pick.player_name || pick.away_team || ''"
      loading="lazy"
      class="w-full h-full object-cover"
      :class="isPlayerProp ? 'rounded-full' : 'rounded'"
      @error="onImgError"
    />
    <Icon
      v-else
      :icon="sportIcons[pick.sport] || 'mdi:trophy'"
      :class="[iconSizeMap[size], sportIconColors[pick.sport] || 'text-gray-400']"
    />
  </div>
</template>
