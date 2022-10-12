import { defineConfig } from 'vite';

const path = require('path');

export default defineConfig({
    publicDir: false,
    appType: 'custom',

    resolve:{
        alias:{
            '@' : path.resolve(__dirname, './assets/javascripts')
        },
    },

    build: {
        outDir: 'public/assets',
        assetsDir: '.',
        sourcemap: true,
        manifest: true,
        rollupOptions: {
            input: {
                'application': './assets/javascripts/application.js',
                'application.css': './assets/stylesheets/application.css',
            },
        },
    },
});
