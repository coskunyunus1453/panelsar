import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  base: '/panelsar/panel/public/',
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        // `panel` için: `php artisan serve --port=8080` (8000 başka projede kullanılıyorsa)
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
    },
  },
})
