import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_DEV_API_TARGET || 'http://127.0.0.1:8000'

  return {
    // O build é servido pelo Laravel a partir de public/app. Para alojar a SPA
    // noutro domínio (Netlify/Vercel), define VITE_BASE_PATH=/.
    base: env.VITE_BASE_PATH || (mode === 'production' ? '/app/' : '/'),

    plugins: [
      vue(),
      mode === 'development' ? vueDevTools() : null,
    ].filter(Boolean),

    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },

    build: {
      outDir: '../public/app',
      emptyOutDir: true,
    },

    server: {
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
