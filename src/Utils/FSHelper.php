<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class FSHelper
{
    /**
     * Read a JSON file and return it as an array.
     *
     * @throws \RuntimeException
     *     Fail if the file cannot be read or if the content is not a valid
     *     JSON array.
     *
     * @return mixed[]
     */
    public static function readJson(string $filepath): array
    {
        $content = file_get_contents($filepath);

        if ($content === false) {
            throw new \RuntimeException("Cannot read the file {$filepath}");
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON file {$filepath}");
        }

        return $data;
    }

    /**
     * List files and directories recursively inside the specified path.
     *
     * Unlike scandir(), this method returns an empty array if $directory is
     * not a directory.
     *
     * Also, the files are never sorted.
     *
     * @return string[]
     */
    public static function recursiveScandir(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $filepaths = [];

        $filenames = scandir($directory, SCANDIR_SORT_NONE);

        if ($filenames === false) {
            return [];
        }

        foreach ($filenames as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $filepath = "{$directory}/{$filename}";
            if (is_dir($filepath)) {
                $filepaths = array_merge($filepaths, self::recursiveScandir($filepath));
            } else {
                $filepaths[] = $filepath;
            }
        }

        return $filepaths;
    }

    /**
     * Delete a file or a directory recursively.
     */
    public static function recursiveUnlink(string $directory): bool
    {
        if (!is_dir($directory)) {
            return unlink($directory);
        }

        $filenames = scandir($directory, SCANDIR_SORT_NONE);
        if ($filenames === false) {
            return true;
        }

        foreach ($filenames as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $filepath = "{$directory}/{$filename}";
            if (is_dir($filepath)) {
                self::recursiveUnlink($filepath);
            } else {
                unlink($filepath);
            }
        }

        return rmdir($directory);
    }
}
