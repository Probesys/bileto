// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

import path from 'path';
import fs from 'fs';

export default function emptyAssetsDirPlugin () {
    let assetsFullDir;

    return {
        name: 'empty-assets-dir-plugin',

        config (userConfig) {
            const { outDir, assetsDir } = userConfig.build;
            assetsFullDir = path.join(outDir, assetsDir);
        },

        buildStart (options) {
            fs.readdir(assetsFullDir, (err, filenames) => {
                if (err) throw err;

                for (const filename of filenames) {
                    if (filename === '.keep') {
                        continue;
                    }

                    fs.unlink(path.join(assetsFullDir, filename), (err) => {
                        if (err) throw err;
                    });
                }
            });
        },
    };
}
