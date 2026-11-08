<?php
/**
 * Endpoint de guardado de la configuración de la Calculadora de Stickers.
 *
 * El frontend (admin) hace POST con JSON { config, materials, shapesCatalog,
 * shapesShowMoreIndex, materialsShowMoreIndex, gallery, deliveryOptions,
 * designOptions } y este script lo persiste en datos_config.json.
 *
 * Seguridad:
 * - Bootstrapea WordPress y exige capacidad de admin (manage_options).
 * - Si el frontend manda `X-WP-Nonce`, se verifica (acción mu-sticker-save).
 * - Valida el esquema: un archivo inválido deja la calculadora sin cargar para
 *   TODOS los clientes, así que acá no se escribe cualquier cosa.
 * - Guarda un backup del archivo anterior antes de reemplazarlo.
 *
 * La lógica vive en mu_sticker_save_config() para poder testearla sin
 * WordPress (definir MU_STICKER_TEST antes de incluir este archivo).
 */

/** true si $value es una lista JSON (índices 0..n-1). */
function mu_sticker_is_list($value) {
    return is_array($value) && array_values($value) === $value;
}

/** Respuesta de error de validación. */
function mu_sticker_invalid($message) {
    return array(422, array('ok' => false, 'error' => $message));
}

/** Códigos repetidos (el codec resolvería siempre el primero). */
function mu_sticker_duplicate_codes($items, $label_key) {
    $by_code = array();
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['code']) || !is_numeric($item['code'])) {
            continue;
        }
        $code = (int) $item['code'];
        $label = isset($item[$label_key]) && is_string($item[$label_key]) ? $item[$label_key] : ('#' . $code);
        if (!isset($by_code[$code])) {
            $by_code[$code] = array();
        }
        $by_code[$code][] = $label;
    }
    $duplicates = array();
    foreach ($by_code as $code => $labels) {
        if (count($labels) > 1) {
            $duplicates[] = $code . ' (' . implode(' / ', $labels) . ')';
        }
    }
    return $duplicates;
}

/**
 * Valida el payload y lo escribe de forma atómica en $targetFile.
 *
 * @param string $raw        Cuerpo crudo del request (JSON).
 * @param string $targetFile Ruta absoluta del datos_config.json a escribir.
 * @return array{0:int,1:array} [código HTTP, cuerpo de respuesta]
 */
function mu_sticker_save_config($raw, $targetFile) {
    if ($raw === '' || $raw === false || $raw === null) {
        return array(400, array('ok' => false, 'error' => 'Cuerpo vacío.'));
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return array(400, array('ok' => false, 'error' => 'JSON inválido.'));
    }

    // --- ESTRUCTURA MÍNIMA ---
    if (!isset($data['config']) || !is_array($data['config'])) {
        return mu_sticker_invalid('Falta "config".');
    }
    if (!isset($data['materials']) || !mu_sticker_is_list($data['materials'])) {
        return mu_sticker_invalid('"materials" debe ser una lista.');
    }
    if (!isset($data['shapesCatalog']) || !is_array($data['shapesCatalog'])) {
        return mu_sticker_invalid('Falta "shapesCatalog".');
    }

    // --- CONFIG: numéricos finitos ---
    $config = $data['config'];
    $numeric_keys = array(
        'monthlySalary', 'weeklyHours', 'hourlyRate', 'packagingCost', 'wasteMargin',
        'profitMargin', 'timeCustomerService', 'timeDelivery', 'timeDesignBasic',
        'timeDesignCustom', 'galleryRefSheets',
    );
    foreach ($numeric_keys as $key) {
        if (!array_key_exists($key, $config)) {
            continue;
        }
        if (!is_numeric($config[$key]) || !is_finite((float) $config[$key])) {
            return mu_sticker_invalid('config.' . $key . ' debe ser un número válido.');
        }
        $config[$key] = (float) $config[$key];
    }
    if (isset($config['calcSalaryMode'])) {
        $config['calcSalaryMode'] = (bool) $config['calcSalaryMode'];
    }

    // --- MATERIALES ---
    $materials = array();
    foreach (array_values($data['materials']) as $index => $material) {
        if (!is_array($material)) {
            return mu_sticker_invalid('materials[' . $index . '] debe ser un objeto.');
        }
        if (!isset($material['id']) || !is_string($material['id']) || trim($material['id']) === '') {
            return mu_sticker_invalid('materials[' . $index . '] no tiene id.');
        }
        if (!isset($material['name']) || !is_string($material['name']) || trim($material['name']) === '') {
            return mu_sticker_invalid('materials[' . $index . '] no tiene nombre.');
        }
        $material['name'] = trim($material['name']);
        $materials[] = $material;
    }
    if (count($materials) === 0) {
        return mu_sticker_invalid('No hay ningún material válido.');
    }
    $duplicate_materials = mu_sticker_duplicate_codes($materials, 'name');
    if (!empty($duplicate_materials)) {
        return mu_sticker_invalid('Códigos de material repetidos: ' . implode('; ', $duplicate_materials) . '.');
    }

    // --- CATÁLOGO DE FORMAS ---
    $catalog = array();
    $flat_sizes = array();
    foreach ($data['shapesCatalog'] as $category => $items) {
        if (!is_string($category) || !mu_sticker_is_list($items)) {
            return mu_sticker_invalid('shapesCatalog.' . $category . ' debe ser una lista.');
        }
        $clean_items = array();
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                return mu_sticker_invalid('shapesCatalog.' . $category . '[' . $index . '] debe ser un objeto.');
            }
            if (!isset($item['size']) || !is_string($item['size']) || trim($item['size']) === '') {
                return mu_sticker_invalid('shapesCatalog.' . $category . '[' . $index . '] no tiene tamaño.');
            }
            $item['size'] = trim($item['size']);
            $clean_items[] = $item;
            $flat_sizes[] = array(
                'code' => isset($item['code']) ? $item['code'] : null,
                'size' => $category . ' ' . $item['size'],
            );
        }
        $catalog[$category] = $clean_items;
    }
    $duplicate_sizes = mu_sticker_duplicate_codes($flat_sizes, 'size');
    if (!empty($duplicate_sizes)) {
        return mu_sticker_invalid('Códigos de tamaño repetidos: ' . implode('; ', $duplicate_sizes) . '.');
    }

    // --- GALERÍA ---
    $gallery = array();
    if (isset($data['gallery'])) {
        if (!mu_sticker_is_list($data['gallery'])) {
            return mu_sticker_invalid('"gallery" debe ser una lista.');
        }
        foreach ($data['gallery'] as $index => $item) {
            if (!is_array($item)) {
                return mu_sticker_invalid('gallery[' . $index . '] debe ser un objeto.');
            }
            if (!isset($item['id']) || !is_string($item['id']) || trim($item['id']) === '') {
                return mu_sticker_invalid('gallery[' . $index . '] no tiene id.');
            }
            $item['order'] = isset($item['order']) && is_string($item['order']) ? trim($item['order']) : '';
            // Una foto sin código no puede cargar nada: se guarda oculta.
            $item['visible'] = $item['order'] !== '' ? (isset($item['visible']) ? (bool) $item['visible'] : true) : false;
            $gallery[] = $item;
        }
    }

    // --- FORMATOS DE ENTREGA / TIPOS DE DISEÑO ---
    // Los ids son strings libres (el Admin puede crear opciones nuevas); se exige
    // id + título no vacíos.
    $options_keys = array('deliveryOptions', 'designOptions');
    $clean_options = array();
    foreach ($options_keys as $key) {
        if (!isset($data[$key])) {
            continue;
        }
        if (!mu_sticker_is_list($data[$key])) {
            return mu_sticker_invalid('"' . $key . '" debe ser una lista.');
        }
        $options = array();
        foreach ($data[$key] as $index => $option) {
            if (!is_array($option)) {
                return mu_sticker_invalid($key . '[' . $index . '] debe ser un objeto.');
            }
            if (!isset($option['id']) || !is_string($option['id']) || trim($option['id']) === '') {
                return mu_sticker_invalid($key . '[' . $index . '] no tiene id.');
            }
            if (!isset($option['label']) || !is_string($option['label']) || trim($option['label']) === '') {
                return mu_sticker_invalid($key . '[' . $index . '] no tiene título.');
            }
            $option['label'] = trim($option['label']);
            $options[] = $option;
        }
        $clean_options[$key] = $options;
    }

// --- PLANTILLAS DEL COTIZADOR (spec 037, Enmienda v2) ---
    // {id, titulo, categoria, pdf, rango{minX,maxX,minY,maxY}, visible, orden}.
    // `pdf` es el slug de un PDF importado; vacio = solo imagen procesada (sin
    // PDF de produccion). El mockup (capas/envolturas) NO vive aca: es del PDF.
    $plantillas = array();
    if (isset($data['plantillas'])) {
        if (!mu_sticker_is_list($data['plantillas'])) {
            return mu_sticker_invalid('"plantillas" debe ser una lista.');
        }
        $plantilla_ids = array();
        foreach ($data['plantillas'] as $index => $plantilla) {
            if (!is_array($plantilla)) {
                return mu_sticker_invalid('plantillas[' . $index . '] debe ser un objeto.');
            }
            $pid = isset($plantilla['id']) && is_string($plantilla['id']) ? trim($plantilla['id']) : '';
            if ($pid === '') {
                return mu_sticker_invalid('plantillas[' . $index . '] no tiene id.');
            }
            if (isset($plantilla_ids[$pid])) {
                return mu_sticker_invalid('plantillas[' . $index . '] repite el id "' . $pid . '".');
            }
            $plantilla_ids[$pid] = true;
            $categoria = isset($plantilla['categoria']) && is_string($plantilla['categoria'])
                ? trim($plantilla['categoria']) : '';
            if ($categoria === '') {
                return mu_sticker_invalid('plantillas[' . $index . '] no tiene categoria.');
            }
            $pdf = isset($plantilla['pdf']) && is_string($plantilla['pdf'])
                ? strtolower(trim($plantilla['pdf'])) : '';
            $pdf = preg_replace('/\.pdf$/', '', $pdf);
            $pdf = preg_replace('/[^a-z0-9_-]/', '', (string) $pdf);
            $plantilla['id'] = $pid;
            $plantilla['categoria'] = $categoria;
            $plantilla['pdf'] = (string) $pdf;
            if (isset($plantilla['rango']) && is_array($plantilla['rango'])) {
                $rg = $plantilla['rango'];
                $minX = (float) ($rg['minX'] ?? 0);
                $maxX = (float) ($rg['maxX'] ?? 0);
                $minY = (float) ($rg['minY'] ?? 0);
                $maxY = (float) ($rg['maxY'] ?? 0);
                if ($maxX > 0 && $maxY > 0 && $minX <= $maxX && $minY <= $maxY) {
                    $plantilla['rango'] = array('minX' => $minX, 'maxX' => $maxX, 'minY' => $minY, 'maxY' => $maxY);
                } else {
                    unset($plantilla['rango']); // rango incoherente: se guarda sin rango
                }
            }
            $plantillas[] = $plantilla;
        }
    }
    // --- ARMADO DEL ARCHIVO ---
    $clean = array(
        'config' => $config,
        'materials' => $materials,
        'shapesCatalog' => $catalog,
    );

    if (isset($data['shapesShowMoreIndex']) && is_array($data['shapesShowMoreIndex'])) {
        $validated_index = array();
        foreach ($data['shapesShowMoreIndex'] as $category => $idx) {
            if (is_string($category) && is_numeric($idx) && isset($catalog[$category])) {
                $idx = intval($idx);
                if ($idx >= 0 && $idx <= count($catalog[$category])) {
                    $validated_index[$category] = $idx;
                }
            }
        }
        if (!empty($validated_index)) {
            $clean['shapesShowMoreIndex'] = $validated_index;
        }
    }

    if (isset($data['materialsShowMoreIndex']) && is_numeric($data['materialsShowMoreIndex'])) {
        $materials_index = intval($data['materialsShowMoreIndex']);
        $clean['materialsShowMoreIndex'] = max(0, min(count($materials), $materials_index));
    }

    if (isset($data['gallery'])) {
        $clean['gallery'] = $gallery;
    }
    foreach ($clean_options as $key => $options) {
        $clean[$key] = $options;
    }

    if (isset($data['plantillas'])) {
        $clean['plantillas'] = $plantillas;
    }
    if (isset($data['tiraNombre']) && is_string($data['tiraNombre'])) {
        $tira = trim($data['tiraNombre']);
        if ($tira !== '') {
            $clean['tiraNombre'] = mb_substr($tira, 0, 60);
        }
    }

    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return array(500, array('ok' => false, 'error' => 'No se pudo serializar el JSON.'));
    }

    $dir = dirname($targetFile);
    if (!is_writable($dir) || (file_exists($targetFile) && !is_writable($targetFile))) {
        return array(500, array('ok' => false, 'error' => 'El archivo de config no es escribible.'));
    }

    // Backup del archivo vigente antes de reemplazarlo.
    if (file_exists($targetFile)) {
        @copy($targetFile, $targetFile . '.bak');
    }

    // Escritura atómica: archivo temporal + rename en el mismo directorio.
    $tmp = tempnam($dir, 'cfg_');
    if ($tmp === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        if ($tmp !== false) {
            @unlink($tmp);
        }
        return array(500, array('ok' => false, 'error' => 'No se pudo escribir el archivo temporal.'));
    }
    if (!rename($tmp, $targetFile)) {
        @unlink($tmp);
        return array(500, array('ok' => false, 'error' => 'No se pudo reemplazar la config.'));
    }
    @chmod($targetFile, 0644);

    return array(200, array('ok' => true));
}

/**
 * Localiza wp-load.php subiendo directorios desde $start.
 *
 * @return string|null Ruta a wp-load.php o null si no se encuentra.
 */
function mu_sticker_find_wp_load($start) {
    $dir = $start;
    for ($i = 0; $i < 10; $i++) {
        $candidate = $dir . '/wp-load.php';
        if (file_exists($candidate)) {
            return $candidate;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break; // Llegamos a la raíz del filesystem.
        }
        $dir = $parent;
    }
    return null;
}

// --- EJECUCIÓN ---
// Se omite cuando el archivo se incluye en un test (MU_STICKER_TEST definido).
if (!defined('MU_STICKER_TEST')) {

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('ok' => false, 'error' => 'Método no permitido.'));
        exit;
    }

    $wpLoad = mu_sticker_find_wp_load(__DIR__);
    if ($wpLoad === null) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'No se pudo cargar WordPress.'));
        exit;
    }
    require_once $wpLoad;

    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'error' => 'No autorizado.'));
        exit;
    }

    // CSRF: si el frontend manda X-WP-Nonce, tiene que ser válido. El shortcode
    // puede inyectar `saveNonce` en WP_STICKER_DATA; mientras no lo haga, la
    // protección sigue siendo la cookie de sesión + capability de admin.
    if (isset($_SERVER['HTTP_X_WP_NONCE'])) {
        $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
        if (!wp_verify_nonce($nonce, 'mu-sticker-save')) {
            http_response_code(403);
            echo json_encode(array('ok' => false, 'error' => 'Nonce inválido.'));
            exit;
        }
    }

    list($code, $body) = mu_sticker_save_config(
        file_get_contents('php://input'),
        __DIR__ . '/datos_config.json'
    );
    http_response_code($code);
    echo json_encode($body);
    exit;
}