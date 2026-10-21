import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// /api va al backend PHP local: mismo origen para la cookie de sesión.
export default defineConfig({ plugins: [vue()], base: './', server: { allowedHosts: ['terminal.local'], proxy: { '/api': 'http://127.0.0.1:8080' } } })
