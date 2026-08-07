/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        ollintem: {
          'blue-emerald': '#0F766E', // Azul esmeralda oscuro (Tono corporativo fuerte)
          'emerald': '#10B981',      // Esmeralda vibrante (Para botones o acentos)
          'mint': '#6EE7B7',         // Verde menta (Para fondos suaves o hover)
          'mint-light': '#D1FAE5',   // Menta muy claro (Para secciones de fondo)
          'dark': '#0F172A',         // Para textos oscuros
        }
      }
    },
  },
  plugins: [],
}