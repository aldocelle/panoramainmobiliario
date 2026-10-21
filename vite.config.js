import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// Las vistas previas (WhatsApp, Facebook) exigen URL absolutas: build.sh define PANORAMA_SITE_URL.
const siteUrl = (process.env.PANORAMA_SITE_URL || 'http://127.0.0.1:5173').replace(/\/$/, '')
const siteMeta = { name: 'panorama-site-url', transformIndexHtml: html => html.replaceAll('__SITE_URL__', siteUrl) }
// /api va al backend PHP local: mismo origen para la cookie de sesión.
export default defineConfig({ plugins: [vue(), siteMeta], base: './', server: { allowedHosts: ['terminal.local'], proxy: { '/api': 'http://127.0.0.1:8080' } } })
