import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Varsayılan `/`: panel alan adı kökündeyken `/admin/...` gibi yollarda sayfa yenilenince
  // `./assets/*` göreli yol `/admin/assets/*` olur ve JS 404 → "Uygulama yükleniyor" takılı kalır.
  // Alt klasör kurulum: build öncesi `VITE_BASE_URL=/hostvim/panel/public/` (sonunda /) verin.
  const base = env.VITE_BASE_URL ?? '/'

  return {
    base,
    plugins: [react()],
    build: {
      // Kurulum çıktısında Monaco vb. uyarıyı gürültü say (hata değil)
      chunkSizeWarningLimit: 2500,
    },
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