/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./**/*.html",
    "./assets/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        primary: '#5C3E35',
        secondary: '#A17A6F',
        accent: '#FBF9F6',
        cream: '#F3EDE6',
        dark: '#2F2522',
        success: '#2E7D32',
        pending: '#EF6C00',
        rejected: '#C62828'
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
        serif: ['"Playfair Display"', 'serif']
      }
    }
  },
  plugins: [],
}
