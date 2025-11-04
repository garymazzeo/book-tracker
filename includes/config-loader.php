<?php
/**
 * Configuration Loader
 * 
 * This file tries to load config.php from multiple locations:
 * 1. Outside web root (most secure): ../config/config.php
 * 2. In project root: ../config.php
 * 3. Current directory: config.php
 * 
 * This allows flexibility while prioritizing security.
 */

$config_paths = [
    __DIR__ . '/../../../../config/config.php',  // Outside web root (recommended)
    __DIR__ . '/../../config/config.php',  // Outside web root (recommended)
    __DIR__ . '/../config/config.php',  // Outside web root (recommended)
    __DIR__ . '/../config.php',          // In project root (fallback)
    __DIR__ . '/config.php',             // Current directory (not recommended)
];

$config_loaded = false;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once $config_path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die("Configuration file not found. Please create config.php in one of these locations:\n" . 
        implode("\n", $config_paths));
}

