/**
 * Draft sound effects using the Web Audio API.
 * No audio files needed — sounds are generated programmatically.
 */

let audioCtx = null

function getContext() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  if (audioCtx.state === 'suspended') {
    audioCtx.resume()
  }
  return audioCtx
}

const TICK_FREQUENCIES = {
  5: 440,
  4: 523,
  3: 587,
  2: 659,
  1: 880,
}

/**
 * Short, sharp tick beep. Pitch increases as secondsLeft decreases (5→1).
 */
export function playTick(secondsLeft) {
  const ctx = getContext()
  const freq = TICK_FREQUENCIES[secondsLeft] || 440
  const duration = 0.08

  const osc = ctx.createOscillator()
  const gain = ctx.createGain()

  osc.type = 'sine'
  osc.frequency.value = freq
  gain.gain.setValueAtTime(0.3, ctx.currentTime)
  gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration)

  osc.connect(gain)
  gain.connect(ctx.destination)
  osc.start(ctx.currentTime)
  osc.stop(ctx.currentTime + duration)
}

/**
 * Two-tone ascending chime indicating it's the user's turn.
 */
export function playYourTurn() {
  const ctx = getContext()
  const note1 = 523 // C5
  const note2 = 659 // E5
  const noteDuration = 0.15
  const gap = 0.05

  // First note
  const osc1 = ctx.createOscillator()
  const gain1 = ctx.createGain()
  osc1.type = 'sine'
  osc1.frequency.value = note1
  gain1.gain.setValueAtTime(0.3, ctx.currentTime)
  gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + noteDuration)
  osc1.connect(gain1)
  gain1.connect(ctx.destination)
  osc1.start(ctx.currentTime)
  osc1.stop(ctx.currentTime + noteDuration)

  // Second note
  const start2 = ctx.currentTime + noteDuration + gap
  const osc2 = ctx.createOscillator()
  const gain2 = ctx.createGain()
  osc2.type = 'sine'
  osc2.frequency.value = note2
  gain2.gain.setValueAtTime(0.3, start2)
  gain2.gain.exponentialRampToValueAtTime(0.01, start2 + noteDuration)
  osc2.connect(gain2)
  gain2.connect(ctx.destination)
  osc2.start(start2)
  osc2.stop(start2 + noteDuration)
}
