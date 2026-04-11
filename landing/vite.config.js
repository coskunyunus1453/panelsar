import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Taban yolu laravel-vite-plugin belirler: ASSET_URL + "build/" (bkz. node_modules/laravel-vite-plugin).
 * ASSET_URL boşken base = "/build/" → chunk preload "/build/assets/..." olur (doğru).
 * Alt klasör (XAMPP): .env içinde ASSET_URL=/hostvim/landing/public → base "/hostvim/landing/public/build/".
 *
 * Sorun: npm run build hâlâ XAMPP .env ile yapılırsa chunk URL'leri kalıcı olarak yanlış gömülür;
 * canlıda (APP_URL kök alan adı) 404 olur. Üretim build'inde tipik kalıntı ASSET_URL için base'i /build/ zorla.
 */
function resolvedViteBaseForBuild(mode) {
    if (mode !== 'production') {
        return undefined;
    }
    const env = loadEnv(mode, process.cwd(), '');
    const assetUrl = (env.ASSET_URL ?? '').trim();
    if (assetUrl === '') {
        return undefined;
    }
    const staleMarkers = [
        '/hostvim/landing/public',
        'hostvim/landing/public',
        '/htdocs/hostvim',
        'localhost/hostvim',
    ];
    if (! staleMarkers.some((needle) => assetUrl.includes(needle))) {
        return undefined;
    }
    try {
        const u = new URL(env.APP_URL || 'https://example.com');
        const path = (u.pathname || '/').replace(/\/$/, '') || '/';
        if (path !== '' && path !== '/') {
            return undefined;
        }
        const host = u.hostname.toLowerCase();
        if (host === 'localhost' || host === '127.0.0.1' || host.endsWith('.test')) {
            return undefined;
        }
    } catch {
        return undefined;
    }

    return '/build/';
}

export default defineConfig(({ mode }) => ({
    base: resolvedViteBaseForBuild(mode),
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
}));
