/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./resources/views/**/*.php', './resources/ts/**/*.ts'],
  theme: {
    extend: {
      colors: {
        primary: '#FE5516',
        beige: '#F5F5DC',
        sand: '#E8D9BB'
      }
    }
  },
  plugins: []
}

