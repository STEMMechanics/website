/** @type {import('tailwindcss').Config} */
export default {
  content: [
      "./resources/**/*.blade.php",
      "./resources/**/*.js",
      './vendor/**/*.blade.php',
  ],
  theme: {
    extend: {
        boxShadow: {
            'deep': '0 10px 15px rgba(0, 0, 0, 0.5)',
        },
        colors: {
            'primary-color': '#0284C7', // sky-600
            'primary-color-dark': '#0370A1', // sky-500
            'primary-color-light': '#0EA5E9', // sky-400

            'danger-color': '#b91c1c', // red-600
            'danger-color-dark': '#991b1b', // red-500
            'danger-color-light': '#dc2626', // red-400

            'success-color': '#16a34a', // green-600
            'success-color-dark': '#22c55e', // green-500
            'success-color-light': '#4ade80', // green-400
        },
    },
  },
  plugins: [],
}

