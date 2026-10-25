import type { Config } from 'tailwindcss';

/**
 * HomeCare Egypt design tokens.
 *
 * Medical blue / white with restrained coral for safety and action. The palette is
 * deliberately narrow so the product reads as clinical and trustworthy rather than
 * as a generic dashboard.
 */
const config: Config = {
  content: ['./src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#EAF3FB',
          100: '#D3E6F6',
          200: '#A7CCEC',
          300: '#75AFE0',
          400: '#4290CF',
          500: '#1267B2', // primary
          600: '#0F5798',
          700: '#0C467A',
          800: '#0A365C',
          900: '#07253F',
        },
        navy: '#102C46',
        coral: {
          50: '#FDECEE',
          100: '#FAD2D7',
          300: '#F0909C',
          500: '#E64255', // safety / destructive action
          600: '#C93043',
          700: '#A52536',
        },
        success: {
          50: '#E7F7F1',
          100: '#C6EEDE',
          500: '#18A879',
          600: '#128763',
        },
        neutral: {
          25: '#FBFDFE',
          50: '#F5F9FC', // soft surface
          100: '#EAF1F7',
          200: '#D7E3EE',
          300: '#B9CBDD',
          400: '#8FA5BA',
          500: '#667D93',
          600: '#4A5F73',
          700: '#33475A',
          800: '#1F3243',
          900: '#102C46',
        },
      },
      fontFamily: {
        sans: ['var(--font-sans)', 'system-ui', 'sans-serif'],
        arabic: ['var(--font-arabic)', 'Tajawal', 'system-ui', 'sans-serif'],
      },
      fontSize: {
        '2xs': ['0.6875rem', { lineHeight: '1rem' }],
      },
      boxShadow: {
        card: '0 1px 2px rgba(16, 44, 70, 0.04), 0 8px 24px -12px rgba(16, 44, 70, 0.12)',
        lift: '0 2px 4px rgba(16, 44, 70, 0.05), 0 16px 40px -16px rgba(16, 44, 70, 0.22)',
        focus: '0 0 0 3px rgba(18, 103, 178, 0.28)',
      },
      borderRadius: {
        xl2: '1.25rem',
      },
      keyframes: {
        'fade-up': {
          from: { opacity: '0', transform: 'translateY(8px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        pulseRing: {
          '0%': { boxShadow: '0 0 0 0 rgba(230, 66, 85, 0.45)' },
          '70%': { boxShadow: '0 0 0 14px rgba(230, 66, 85, 0)' },
          '100%': { boxShadow: '0 0 0 0 rgba(230, 66, 85, 0)' },
        },
      },
      animation: {
        'fade-up': 'fade-up 0.35s ease-out both',
        'pulse-ring': 'pulseRing 2s infinite',
      },
    },
  },
  plugins: [],
};

export default config;