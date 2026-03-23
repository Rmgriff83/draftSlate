<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip)

const props = defineProps({
  games: { type: Array, required: true },
  threshold: { type: Number, required: true },
  statLabel: { type: String, default: 'PTS' },
})

const canvasRef = ref(null)
let chartInstance = null

// Inline plugin to draw a dashed threshold line
const thresholdLinePlugin = {
  id: 'thresholdLine',
  afterDraw(chart) {
    const { ctx, scales } = chart
    const yScale = scales.y
    if (!yScale) return

    const yPos = yScale.getPixelForValue(props.threshold)
    const { left, right } = chart.chartArea

    ctx.save()
    ctx.beginPath()
    ctx.setLineDash([6, 4])
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.5)'
    ctx.lineWidth = 1.5
    ctx.moveTo(left, yPos)
    ctx.lineTo(right, yPos)
    ctx.stroke()
    ctx.restore()

    // Label
    ctx.save()
    ctx.fillStyle = 'rgba(255, 255, 255, 0.6)'
    ctx.font = '10px sans-serif'
    ctx.textAlign = 'right'
    ctx.fillText(props.threshold.toString(), right - 4, yPos - 4)
    ctx.restore()
  },
}

function buildChart() {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }

  const ctx = canvasRef.value?.getContext('2d')
  if (!ctx || !props.games.length) return

  // Reverse so chart reads oldest → newest (left to right)
  const reversed = [...props.games].reverse()
  const labels = reversed.map((g) => g.opponent)
  const values = reversed.map((g) => g.stat_value)
  const colors = reversed.map((g) =>
    g.hit ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)'
  )

  const maxVal = Math.max(...values, props.threshold)

  chartInstance = new Chart(ctx, {
    type: 'bar',
    plugins: [thresholdLinePlugin],
    data: {
      labels,
      datasets: [
        {
          data: values,
          backgroundColor: colors,
          borderRadius: 4,
          barPercentage: 0.6,
          categoryPercentage: 0.7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1f2937',
          titleColor: '#d1d5db',
          bodyColor: '#f3f4f6',
          borderColor: '#374151',
          borderWidth: 1,
          callbacks: {
            label(item) {
              return `${props.statLabel}: ${item.raw}`
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: '#9ca3af',
            font: { size: 11, weight: 'bold' },
          },
        },
        y: {
          beginAtZero: true,
          suggestedMax: maxVal * 1.15,
          grid: { color: 'rgba(75, 85, 99, 0.2)' },
          ticks: {
            color: '#6b7280',
            font: { size: 10 },
            stepSize: Math.ceil(maxVal / 4),
          },
        },
      },
    },
  })
}

watch(() => props.games, buildChart, { deep: true })

onMounted(buildChart)

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
})
</script>

<template>
  <div class="h-[180px]">
    <canvas ref="canvasRef"></canvas>
  </div>
</template>
