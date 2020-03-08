<?php

namespace BOS\Player\Utils;

class BuiltinWebserver {

    /**
     * Try serve static files from catalogue module directories.
     * 
     * Supports:
     * 
     * GET /valid-module-name/path/to/file.txt
     * GET /any-partition-name/valid-module-name/path/to/file.txt
     * 
     * Rules:
     * - Files have an extension
     * - Files must not be dot-files.
     * 
     * 
     * 
     */
    static function tryServeStatics() {
        $uri = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/');

        $basename = basename($uri);
        $extension = pathinfo($basename, PATHINFO_EXTENSION);

        if (!$basename || $basename{0} === '.') {
            return;
        }

        if (!$extension || $extension === 'php') {
            return;
        }

        $catalogueDir = $_ENV['CATALOGUE_DIR'];

        // extract the module bit...
        // url is either:
        // module/path/to/file.js 
        // partition/module/path/to/file.js 
        // environment/module/path/to/file.js
        // environment/partition/module/path/to/file.js

        $pieces = explode("/", $uri);

        $tryUrls = array_filter([
            array_slice($pieces, 2),
            array_slice($pieces, 1),
            $pieces
        ]);

        foreach ($tryUrls as $try) {
            $potentialModule = $try[0];

            if (is_dir("$catalogueDir/$potentialModule")) {
                $module = $try[0];
                $filename = join("/", array_slice($try, 1));
                break;
            }
        }

        $paths = [
            "$module/public/$filename",
            "$module/dist/$filename",
            "$module/build/$filename"
        ];

        foreach ($paths as $p) {
            $fullPath = "$catalogueDir/$p";

            if (file_exists($fullPath)) {
                static::serveFileAndExit($fullPath);
            }
        }
    }


    static function serveFileAndExit($possibleFile) {
        // @todo - beveiligen van extensies, zoals php, .env, etc
        $basename = basename($possibleFile);
        $extension = pathinfo($possibleFile, PATHINFO_EXTENSION);

        // @fixme - security, dont allow ../ in this path.
        // if (strpos($possibleFile, '../') !== false) {
        //     throw new \Exception('Not allowed');
        // }

        if (!$basename || $basename{0} === '.') {
            return false;
        }

        if (!$extension || $extension === 'php') {
            return false;
        }

        if (is_file($possibleFile)) {            
            $mimes = new \Mimey\MimeTypes;
            header('Content-Type: ' . $mimes->getMimeType($extension));
            readfile($possibleFile);
            exit;
        }
        return false;
    }
}