import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        ds: {
          // Brand — mapped to CSS vars (same in both modes)
          primary: 'var(--ds-teal-primary)',
          'primary-light': 'var(--ds-teal-highlight)',
          'primary-dark': 'var(--ds-teal-primary)',
          'teal-primary': 'var(--ds-teal-primary)',
          'teal-highlight': 'var(--ds-teal-highlight)',

          // Semantic states — mode-aware via CSS vars
          green: 'var(--ds-success)',
          red: 'var(--ds-danger)',
          yellow: 'var(--ds-warning)',
          gold: '#FFB800',
          success: 'var(--ds-success)',
          'success-bg': 'var(--ds-success-bg)',
          danger: 'var(--ds-danger)',
          'danger-bg': 'var(--ds-danger-bg)',
          warning: 'var(--ds-warning)',
          'warning-bg': 'var(--ds-warning-bg)',
          pending: 'var(--ds-pending)',
          locked: 'var(--ds-locked)',

          // Surfaces — mode-aware via CSS vars
          bg: {
            primary: 'var(--ds-bg-primary)',
            secondary: 'var(--ds-bg-surface)',
            tertiary: 'var(--ds-bg-surface-raised)',
            hover: 'var(--ds-bg-surface-raised)',
            surface: 'var(--ds-bg-surface)',
            'surface-raised': 'var(--ds-bg-surface-raised)',
            'teal-deep': 'var(--ds-bg-teal-deep)',
            'teal-wash': 'var(--ds-bg-teal-wash)',
          },

          // Text — mode-aware via CSS vars
          text: {
            primary: 'var(--ds-text-primary)',
            secondary: 'var(--ds-text-secondary)',
            tertiary: 'var(--ds-text-tertiary)',
            teal: 'var(--ds-text-teal)',
          },

          // Borders — mode-aware via CSS vars
          border: 'var(--ds-border-default)',
          'border-teal': 'var(--ds-border-teal)',
          'border-subtle': 'var(--ds-border-subtle)',
        },
      },
      fontFamily: {
        sans: ['DM Sans', 'Inter', ...defaultTheme.fontFamily.sans],
        mono: ['JetBrains Mono', 'Fira Code', ...defaultTheme.fontFamily.mono],
      },
      borderRadius: {
        ds: '12px',
        'ds-sm': '6px',
        'ds-lg': '16px',
        'ds-xl': '24px',
      },
      boxShadow: {
        'ds-sm': '0 1px 3px rgba(0, 0, 0, 0.3)',
        'ds-md': '0 4px 12px rgba(0, 0, 0, 0.4)',
        'ds-lg': '0 8px 24px rgba(0, 0, 0, 0.5)',
        'ds-glow': '0 0 20px rgba(13, 148, 136, 0.3)',
        'ds-hit': '0 0 16px rgba(34, 197, 94, 0.4)',
        'ds-miss': '0 0 16px rgba(239, 68, 68, 0.4)',
        'ds-live': '0 0 12px rgba(34, 197, 94, 0.4), 0 0 0 1px rgba(34, 197, 94, 0.3)',
        'ds-live-bright': '0 0 24px rgba(34, 197, 94, 0.7), 0 0 0 2px rgba(34, 197, 94, 0.5)',
      },
      transitionTimingFunction: {
        'ds-out': 'cubic-bezier(0.16, 1, 0.3, 1)',
        'ds-in-out': 'cubic-bezier(0.65, 0, 0.35, 1)',
        'ds-bounce': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
        'ds-spring': 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
      },
      transitionDuration: {
        'ds-fast': '150ms',
        'ds-normal': '250ms',
        'ds-slow': '400ms',
        'ds-dramatic': '800ms',
      },
      animation: {
        'hit-flash': 'hitFlash 800ms cubic-bezier(0.34, 1.56, 0.64, 1)',
        'miss-flash': 'missFlash 800ms cubic-bezier(0.16, 1, 0.3, 1)',
        'pulse-red': 'pulseRed 1s ease-in-out infinite',
        'score-pop': 'scorePop 400ms cubic-bezier(0.34, 1.56, 0.64, 1)',
        'slide-up': 'slideUp 400ms cubic-bezier(0.16, 1, 0.3, 1)',
        'live-glow': 'liveGlow 2s ease-in-out infinite',
      },
      keyframes: {
        hitFlash: {
          '0%': { boxShadow: '0 0 0 rgba(34,197,94,0)', transform: 'scale(1)' },
          '30%': { boxShadow: '0 0 24px rgba(34,197,94,0.6)', transform: 'scale(1.03)' },
          '100%': { boxShadow: '0 0 8px rgba(34,197,94,0.2)', transform: 'scale(1)' },
        },
        missFlash: {
          '0%': { boxShadow: '0 0 0 rgba(239,68,68,0)', transform: 'translateX(0)' },
          '20%': { boxShadow: '0 0 24px rgba(239,68,68,0.6)', transform: 'translateX(-3px)' },
          '40%': { transform: 'translateX(3px)' },
          '60%': { transform: 'translateX(-2px)' },
          '100%': { boxShadow: '0 0 8px rgba(239,68,68,0.2)', transform: 'translateX(0)' },
        },
        pulseRed: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.5', color: 'var(--ds-danger)' },
        },
        scorePop: {
          '0%': { transform: 'scale(1)' },
          '50%': { transform: 'scale(1.2)' },
          '100%': { transform: 'scale(1)' },
        },
        slideUp: {
          '0%': { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        liveGlow: {
          '0%, 100%': {
            boxShadow: '0 0 10px rgba(34, 197, 94, 0.3), 0 0 0 1px rgba(34, 197, 94, 0.25)',
          },
          '50%': {
            boxShadow: '0 0 28px rgba(34, 197, 94, 0.7), 0 0 0 2px rgba(34, 197, 94, 0.5)',
          },
        },
      },
    },
  },
  plugins: [forms],
}
