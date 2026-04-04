import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Alt klasör (örn. XAMPP: /panelsar/panel/public/) ve kök kurulumda asset 404 olmaması için
  // varsayılan göreli taban. Kökte sabit / istiyorsanız: VITE_BASE_URL=/
  const base = env.VITE_BASE_URL ?? './'

  return {
    base,
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
        // SPA `index.php/api` ile çağırıyor; dev sunucuda aynı hedefe yönlendir.
        '/index.php': {
          target: 'http://127.0.0.1:8080',
          changeOrigin: true,
        },
      },
    },
  }
})