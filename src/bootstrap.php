<?php
/**
 * Bootstrap File for LaporFasilitas Application
 */

// Define Root Path
define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Autoload Classes PSR-4 Style
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    
    // Check in config directory
    if (str_starts_with($relativeClass, 'Config\\')) {
        $file = CONFIG_PATH . '/' . str_replace('\\', '/', substr($relativeClass, 7)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Default: Check in src directory
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Config
$config = require_once CONFIG_PATH . '/app.php';

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * URL and Asset Helper for both Root and Subfolder environments
 */
function base_url(string $path = ''): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $baseDir = rtrim(dirname($scriptName), '/');
    if ($baseDir === '/' || $baseDir === '\\') {
        $baseDir = '';
    }
    $path = '/' . ltrim($path, '/');
    return $baseDir . ($path === '/' ? ($baseDir === '' ? '/' : '') : $path);
}

function asset(string $path): string {
    return base_url($path);
}

function url(string $path = ''): string {
    return base_url($path);
}

/**
 * View Renderer Helper
 */
function view(string $viewName, array $data = []): void {
    extract($data);
    $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $viewName) . '.php';
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        http_response_code(404);
        echo "<h1>View [{$viewName}] Not Found</h1>";
    }
}

