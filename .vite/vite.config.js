import { defineConfig } from 'vite';
import emptyAssetsDirPlugin from './empty-assets-dir-plugin.js';

const path = require('path');

export default defineConfig({
    publicDir: false,
    appType: 'custom',

    plugins: [
        emptyAssetsDirPlugin(),
    ],

    resolve:{
        alias:{
            '@' : path.resolve(__dirname, './assets/javascripts')
        },
    },

    build: {
        outDir: 'public',
        assetsDir: 'assets',
        sourcemap: true,
        manifest: true,
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'application': './assets/javascripts/application.js',
                'application.css': './assets/stylesheets/application.css',
            },
        },
    },
});
