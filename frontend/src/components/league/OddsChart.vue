<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Chart, LineController, LineElement, PointElement, LinearScale, TimeScale, Tooltip, Filler } from 'chart.js'
import api from '@/utils/api'

Chart.register(LineController, LineElement, PointElement, LinearScale, TimeScale, Tooltip, Filler)

const props = defineProps({
  pickSelectionId: { type: Number, required: true },
})

const canvasRef = ref(null)
const loading = ref(true)
const empty = ref(false)
let chartInstance = null

function formatTime(dateStr) {
  const d = new Date(dateStr)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) +
    ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

function formatOdds(val) {
  if (val == null) return ''
  return val > 0 ? `+${val}` : `${val}`
}

async function loadChart() {
  loading.value = true
  empty.value = false

  try {
    const { data } = await api.get(`/api/v1/picks/${props.pickSelectionId}/odds-history`)

    const snapshots = data.snapshots || []
    const lifecycle = data.lifecycle || []

    if (snapshots.length === 0 && lifecycle.length === 0) {
      empty.value = true
      loading.value = false
      return
    }

    // Build data points from snapshots
    const dataPoints = snapshots.map(s => ({
      x: new Date(s.captured_at).getTime(),
      y: s.odds,
    }))

    // Build lifecycle annotation points
    const lifecyclePoints = lifecycle.map(l => ({
      x: new Date(l.at).getTime(),
      y: l.odds,
      label: l.label,
    }))

    // Merge all points and sort by time for the main line
    const allPoints = [...dataPoints, ...lifecyclePoints.map(l => ({ x: l.x, y: l.y }))]
    allPoints.sort((a, b) => a.x - b.x)

    // Deduplicate by x value (keep first occurrence)
    const seen = new Set()
    const uniquePoints = allPoints.filter(p => {
      if (seen.has(p.x)) return false
      seen.add(p.x)
      return true
    })

    if (chartInstance) {
      chartInstance.destroy()
      chartInstance = null
    }

    const ctx = canvasRef.value?.getContext('2d')
    if (!ctx) return

    chartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        datasets: [
          {
            label: 'Odds',
            data: uniquePoints,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.08)',
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            pointBackgroundColor: '#10b981',
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Lifecycle',
            data: lifecyclePoints,
            borderColor: 'transparent',
            backgroundColor: 'transparent',
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: lifecyclePoints.map(l =>
              l.label === 'Drafted' ? '#3b82f6' :
              l.label === 'Locked' ? '#f59e0b' :
              '#6b7280'
            ),
            pointBorderColor: lifecyclePoints.map(l =>
              l.label === 'Drafted' ? '#3b82f6' :
              l.label === 'Locked' ? '#f59e0b' :
              '#6b7280'
            ),
            pointBorderWidth: 2,
            pointStyle: 'circle',
            showLine: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        parsing: false,
        interaction: {
          mode: 'nearest',
          intersect: false,
        },
        plugins: {
          tooltip: {
            backgroundColor: '#1f2937',
            titleColor: '#d1d5db',
            bodyColor: '#f3f4f6',
            borderColor: '#374151',
            borderWidth: 1,
            callbacks: {
              title(items) {
                if (!items.length) return ''
                return formatTime(new Date(items[0].parsed.x))
              },
              label(item) {
                // Check if this is a lifecycle point
                if (item.datasetIndex === 1) {
                  const lp = lifecyclePoints[item.dataIndex]
                  return lp ? `${lp.label}: ${formatOdds(item.parsed.y)}` : formatOdds(item.parsed.y)
                }
                return `Odds: ${formatOdds(item.parsed.y)}`
              },
            },
          },
        },
        scales: {
          x: {
            type: 'linear',
            display: true,
            grid: { color: 'rgba(75, 85, 99, 0.3)' },
            ticks: {
              color: '#6b7280',
              font: { size: 9 },
              maxTicksLimit: 5,
              callback(val) {
                return formatTime(new Date(val))
              },
            },
          },
          y: {
            display: true,
            grid: { color: 'rgba(75, 85, 99, 0.3)' },
            ticks: {
              color: '#6b7280',
              font: { size: 10 },
              callback(val) {
                return formatOdds(val)
              },
            },
          },
        },
      },
    })
  } catch (err) {
    console.error('Failed to load odds history:', err)
    empty.value = true
  } finally {
    loading.value = false
  }
}

watch(() => props.pickSelectionId, () => {
  loadChart()
})

onMounted(() => {
  loadChart()
})

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
})
</script>

<template>
  <div class="ds-card bg-ds-bg-hover p-4">
    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Line Movement</h4>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center h-[150px]">
      <div class="w-5 h-5 border-2 border-ds-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="empty" class="flex items-center justify-center h-[150px]">
      <p class="text-xs text-gray-500">No line movement data yet</p>
    </div>

    <!-- Chart -->
    <div v-show="!loading && !empty" class="h-[150px]">
      <canvas ref="canvasRef"></canvas>
    </div>

    <!-- Legend -->
    <div v-if="!loading && !empty" class="flex items-center gap-4 mt-2">
      <div class="flex items-center gap-1">
        <span class="w-2 h-2 rounded-full bg-gray-500"></span>
        <span class="text-[9px] text-gray-500">Created</span>
      </div>
      <div class="flex items-center gap-1">
        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
        <span class="text-[9px] text-gray-500">Drafted</span>
      </div>
      <div class="flex items-center gap-1">
        <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
        <span class="text-[9px] text-gray-500">Locked</span>
      </div>
    </div>
  </div>
</template>
