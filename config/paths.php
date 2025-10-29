<?php
/**
 * config/paths.php
 * Configuración de rutas del proyecto
 */

// Detectar el directorio base del proyecto
$baseDir = str_replace('\\', '/', dirname(__DIR__));

// Detectar la URL base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Obtener el path del script actual
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Calcular la base URL
// Si estamos en views/pages/, subimos dos niveles
if (strpos($scriptPath, '/views/pages') !== false) {
    $baseUrl = str_replace('/views/pages', '', $scriptPath);
} elseif (strpos($scriptPath, '/views') !== false) {
    $baseUrl = str_replace('/views', '', $scriptPath);
} else {
    $baseUrl = $scriptPath;
}

// Limpiar barras duplicadas
$baseUrl = preg_replace('#/+#', '/', $baseUrl);
$baseUrl = rtrim($baseUrl, '/');

// Si está en la raíz, dejar vacío
if ($baseUrl === '') {
    $baseUrl = '';
}

// Definir constantes
define('BASE_PATH', $baseDir);
define('BASE_URL', $baseUrl);

/**
 * Helper functions para generar URLs
 */
function asset($path) {
    $path = ltrim($path, '/');
    return BASE_URL . '/views/assets/' . $path;
}

function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

function controller($path) {
    $path = ltrim($path, '/');
    return BASE_URL . '/controllers/' . $path;
}

function view($path) {
    $path = ltrim($path, '/');
    return BASE_URL . '/views/pages/' . $path;
}
?>