import { defineConfig } from 'vite';

export default defineConfig({
    publicDir: false,
    build: {
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/prepilot.js',
            formats: ['iife'],
            name: 'PaytakhtScanner',
            fileName: () => 'prepilot.js',
        },
        outDir: 'public/assets',
        sourcemap: false,
    },
});
