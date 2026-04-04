import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Taban yolu laravel-vite-plugin belirler: ASSET_URL + "build/" (bkz. node_modules/laravel-vite-plugin).
 * ASSET_URL boşken base = "/build/" → chunk preload "/build/assets/..." olur (doğru).
 * Alt klasör (XAMPP): .env içinde ASSET_URL=/panelsar/landing/public → base "/panelsar/landing/public/build/".
 * Burada base vermeyin; aksi halde eklentinin hesabı ezilir ve preload 404 verir.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
