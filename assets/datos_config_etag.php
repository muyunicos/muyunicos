<?php
/**
 * Endpoint con ETag para servir datos_config.json con caché inteligente.
 * 
 * Este endpoint implementa validación condicional usando ETag:
 * - Si el archivo no cambió, devuelve 304 Not Modified (casi sin CPU)
 * - Si el archivo cambió, devuelve el contenido nuevo
 * - Cache-Control de 1 minuto para permitir caché intermedia
 * 
 * Esto reduce significativamente el consumo de CPU del servidor mientras
 * asegura que los cambios de precios sean visibles en máximo 1 minuto.
 */

$configFile = __DIR__ . '/datos_config.json';

// Verificar que el archivo exista
if (!file_exists($configFile)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'datos_config.json no encontrado']);
    exit;
}

// Generar ETag basado en el contenido del archivo
$etag = md5_file($configFile);
$lastModified = @filemtime($configFile);

// Configurar headers de caché
header("ETag: \"$etag\"");
header('Cache-Control: public, max-age=60'); // 1 minuto de caché
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if ($lastModified !== false) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
}

// Validación condicional del If-None-Match.
// Soporta ETags débiles (W/"hash"), listas separadas por coma y el comodín "*".
// Antes se comparaba el string exacto: un ETag débil o una lista devolvían 200
// aunque el contenido no hubiera cambiado.
$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
$notModified = false;
if ($ifNoneMatch !== '') {
    foreach (explode(',', $ifNoneMatch) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*') {
            $notModified = true;
            break;
        }
        if (strncmp($candidate, 'W/', 2) === 0) {
            $candidate = substr($candidate, 2);
        }
        if (trim($candidate, '"') === $etag) {
            $notModified = true;
            break;
        }
    }
}

if ($notModified) {
    // El cliente ya tiene la versión más reciente
    http_response_code(304);
    exit;
}

// Si llegamos aquí, el cliente necesita el contenido actual
readfile($configFile);
