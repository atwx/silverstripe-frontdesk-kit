import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'client/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                frontdesk: resolve(__dirname, 'client/src/js/main.js'),
            },
            output: [
                {
                    // JS bundle
                    format: 'iife',
                    entryFileNames: 'frontdesk.js',
                    assetFileNames: 'frontdesk.css',
                    inlineDynamicImports: true,
                },
            ],
        },
    },
});
