<?php

namespace BOS\Player\Utils;

class UploadFolder {
    function __construct($basePath, $nested = false) {
        $this->basePath = $basePath;
        $this->path = null;
        $this->allowDirectories = $nested;
    }

    function setDirectory($path) {
        $this->path = $path;

        if (!is_dir($this->getFullPath())) {
            mkdir($this->getFullPath(), 0777, true);
        }
    }

    function getFullPath() {
        return "{$_ENV['HOME']}/{$this->basePath}/{$this->path}/";
    }

    function serve($filename) {
        $basename = basename($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (!$basename || $basename{0} === '.') {
            header('HTTP/1.1 403 Not allowed');
            echo htmlspecialchars($filename) . ' not allowed.';
            exit;
        }

        if (!$extension || $extension === 'php') {
            header('HTTP/1.1 403 Not allowed');
            echo htmlspecialchars($filename) . ' not allowed.';
            exit;
        }

        if (is_file($this->getFullPath() . "/$filename")) {            
            $mimes = new \Mimey\MimeTypes;
            header('Content-Type: ' . $mimes->getMimeType($extension));
            readfile($this->getFullPath() . "/$filename");
            exit;
        } else {
            header('HTTP/1.1 404 Not found');
            echo htmlspecialchars($filename) . ' not found.';
            exit;
        }

        return false;
    }

    function moveUpload($tempFile, $name) {
        $targetFile = $this->getFullPath() . basename($name);
        return move_uploaded_file($tempFile, $targetFile);
    }

    function add($filename, $data) {
        if (strpos($filename, '../') !== false) {
            throw new \Exception('Not allowed: Tried to access a file with ../ in it.');
        }

        return file_put_contents($this->getFullPath() . "/$filename", $data);
    }

    function delete($filename) {
        return unlink($this->getFullPath() . "/$filename");
    }
}