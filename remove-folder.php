<?php
$directory = __DIR__ . '/vendor/wrightwaydigitalltd';

if (is_dir($directory)) {
    $it = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($directory);
    echo "Removed folder: $directory" . PHP_EOL;
} else {
    echo "Folder not found: $directory" . PHP_EOL;
}
