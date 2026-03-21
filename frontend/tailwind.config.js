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
          primary: '#6C3FE0',
          'primary-light': '#8B6CE6',
          'primary-dark': '#4A1FB8',
          green: '#00D26A',
          red: '#FF3B5C',
          gold: '#FFB800',
          blue: '#2B7FFF',
          bg: {
            primary: '#0D0F14',
            secondary: '#161A22',
            tertiary: '#1E232E',
            hover: '#252B38',
          },
          text: {
            primary: '#FFFFFF',
            secondary: '#8E95A8',
            tertiary: '#5A6178',
          },
          border: '#2A2F3D',
        },
      },
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
        mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
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
        'ds-glow': '0 0 20px rgba(108, 63, 224, 0.3)',
        'ds-hit': '0 0 16px rgba(0, 210, 106, 0.4)',
        'ds-miss': '0 0 16px rgba(255, 59, 92, 0.4)',
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
      },
      keyframes: {
        hitFlash: {
          '0%': { boxShadow: '0 0 0 rgba(0,210,106,0)', transform: 'scale(1)' },
          '30%': { boxShadow: '0 0 24px rgba(0,210,106,0.6)', transform: 'scale(1.03)' },
          '100%': { boxShadow: '0 0 8px rgba(0,210,106,0.2)', transform: 'scale(1)' },
        },
        missFlash: {
          '0%': { boxShadow: '0 0 0 rgba(255,59,92,0)', transform: 'translateX(0)' },
          '20%': { boxShadow: '0 0 24px rgba(255,59,92,0.6)', transform: 'translateX(-3px)' },
          '40%': { transform: 'translateX(3px)' },
          '60%': { transform: 'translateX(-2px)' },
          '100%': { boxShadow: '0 0 8px rgba(255,59,92,0.2)', transform: 'translateX(0)' },
        },
        pulseRed: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.5', color: '#FF3B5C' },
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
      },
    },
  },
  plugins: [forms],
}
