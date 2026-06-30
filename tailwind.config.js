import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

export default {
  darkMode: 'class',
  content: ['./resources/**/*.blade.php', './resources/**/*.js', './app/View/**/*.php'],
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', ...defaultTheme.fontFamily.sans] },
      colors: {
        accent: '#e3fd57',
        brand: { 500: '#4d6cff', 600: '#3449f5', 950: '#171a4d' }
      },
      boxShadow: { glow: '0 20px 90px rgba(77,108,255,.24)' }
    }
  },
  plugins: [forms, typography],
};
