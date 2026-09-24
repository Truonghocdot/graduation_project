import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { loadEnv } from 'vite';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerHost = env.VITE_DEV_SERVER_HOST || '127.0.0.1';

    return {
        plugins: lazyPlugins(() => [
            laravel({
                input: [
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
                'resources/js/app.js',
                    'resources/js/passkeys.js',
                ],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
        ]),
        server: {
            host: devServerHost,
            hmr: {
                host: devServerHost,
            },
            cors: true,
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/storage/framework/views/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
