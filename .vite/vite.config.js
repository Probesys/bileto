import { defineConfig } from 'vite';
import autoprefixer from 'autoprefixer';
import emptyAssetsDirPlugin from './empty-assets-dir-plugin.js';

const path = require('path');

export default defineConfig(({ mode }) => {
    const buildConfig = {
        outDir: 'public',
        sourcemap: true,
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'application': './assets/javascripts/application.js',
                'application.css': './assets/stylesheets/application.css',
            },
        },
    };

    if (mode === 'production') {
        buildConfig.assetsDir = 'assets';
        buildConfig.manifest = 'manifest.json';
        buildConfig.minify = true;
    } else {
        buildConfig.assetsDir = 'dev_assets';
        buildConfig.manifest = 'manifest.dev.json';
        buildConfig.minify = false;
    }

    return {
        publicDir: false,
        appType: 'custom',

        plugins: [
            emptyAssetsDirPlugin(),
        ],

        resolve:{
            alias:{
                '@' : path.resolve(__dirname, '../assets/javascripts')
            },
        },

        build: buildConfig,

        css: {
            postcss: {
                plugins: [autoprefixer],
            },
        },
    };
});
