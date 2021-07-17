<?php

// Load Class Dynamically
spl_autoload_register(function ($className) {
    $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
    $filePath = __DIR__ . '/src/' . $className . '.php';

    if (file_exists($filePath)) {
        require $filePath;
    }
});
