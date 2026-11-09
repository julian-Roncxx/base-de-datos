<?php
/**
 * Instalador seguro de la interfaz de alertas de UNDERSTOCK.
 * Ejecutar exclusivamente por CLI desde la carpeta UNDSTK:
 * C:\xampp\php\php.exe integrar_alertas.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ejecuta este script únicamente desde una terminal.');
}

$raiz = __DIR__;
$origen = $raiz . DIRECTORY_SEPARATOR . 'archivos';
$principal = $raiz . '/public/views/main.html';

if (!is_file($principal) || !is_file($raiz . '/public/index.php') || !is_dir($raiz . '/src')) {
    fwrite(STDERR, "ERROR: copia integrar_alertas.php y la carpeta archivos/ DENTRO de UNDSTK.\n");
    exit(1);
}
if (!is_dir($origen)) {
    fwrite(STDERR, "ERROR: falta la carpeta archivos/ del paquete.\n");
    exit(1);
}

$respaldo = $raiz . '/.respaldo_alertas_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
if (!mkdir($respaldo, 0755, true) && !is_dir($respaldo)) {
    throw new RuntimeException('No se pudo crear la carpeta de respaldo.');
}

function respaldar(string $rutaRelativa): void
{
    global $raiz, $respaldo;
    $destino = $raiz . '/' . $rutaRelativa;
    if (!file_exists($destino)) return;
    $copia = $respaldo . '/' . $rutaRelativa;
    $carpeta = dirname($copia);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No se pudo crear ' . $carpeta);
    }
    if (!copy($destino, $copia)) {
        throw new RuntimeException('No se pudo respaldar ' . $rutaRelativa);
    }
}

function guardar(string $rutaRelativa, string $contenido): void
{
    global $raiz;
    $destino = $raiz . '/' . $rutaRelativa;
    $carpeta = dirname($destino);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No se pudo crear la carpeta ' . $carpeta);
    }
    if (file_put_contents($destino, $contenido) === false) {
        throw new RuntimeException('No se pudo escribir ' . $rutaRelativa);
    }
    echo "Actualizado: {$rutaRelativa}\n";
}

$reemplazos = [
    'src/config/MongoDatabase.php',
    'src/Models/InventoryAlert.php',
    'src/Services/InventoryAlertService.php',
    'public/alertas_local.php',
    'public/views/alertas_demo.html',
    'public/css/alertas-ui.css',
    'public/js/alertas-ui.js',
];

try {
    // Respaldo antes de sobrescribir, incluyendo la vista principal.
    foreach (array_merge($reemplazos, ['public/views/main.html']) as $path) {
        respaldar($path);
    }
    foreach ($reemplazos as $path) {
        $content = file_get_contents($origen . '/' . $path);
        if ($content === false) throw new RuntimeException('Falta el recurso ' . $path);
        guardar($path, $content);
    }

    $contenido = file_get_contents($principal);
    $seccion = file_get_contents($origen . '/alertas_seccion.html');
    if ($contenido === false || $seccion === false) {
        throw new RuntimeException('No se pudo leer la vista existente o el nuevo módulo.');
    }

    $patron = '~<section\b[^>]*\bid\s*=\s*[\x22\x27]alertas[\x22\x27][^>]*>.*?</section>~is';
    $contar = 0;
    $contenido = preg_replace_callback($patron, function () use ($seccion) { return $seccion; }, $contenido, 1, $contar);
    if ($contenido === null) throw new RuntimeException('Error al procesar sección alertas.');
    if ($contar === 0) {
        $ancla = '<section id="ventas"';
        $pos = strpos($contenido, $ancla);
        if ($pos === false) throw new RuntimeException('No se encontró dónde insertar Alertas en main.html.');
        $contenido = substr_replace($contenido, $seccion . "\n\n    ", $pos, 0);
    }

    if (!str_contains($contenido, 'id="link-alertas"')) {
        $patronNav = '~(<a[^>]+id="link-inventario"[^>]*>.*?</a>)~is';
        $contenido = preg_replace($patronNav,
            '$1' . "\n      " . '<a href="#" id="link-alertas" onclick="showSection(\'alertas\')">🔔 Alertas Inteligentes</a>',
            $contenido, 1, $navMatches);
        if (!$navMatches) throw new RuntimeException('No se encontró el menú Inventario en main.html.');
    }

    if (!str_contains($contenido, '../css/alertas-ui.css')) {
        $contenido = str_replace('</head>', '  <link rel="stylesheet" href="../css/alertas-ui.css">' . "\n" . '</head>', $contenido);
    }
    if (!str_contains($contenido, '../js/alertas-ui.js')) {
        $contenido = str_replace('</body>', '  <script defer src="../js/alertas-ui.js"></script>' . "\n" . '</body>', $contenido);
    }

    // Si se instala en una rama sin el enlace showSection, añadir el evento.
    if (!str_contains($contenido, "sectionId === 'alertas'")) {
        $s = "if (sectionId === 'bd') { cargarBaseDatos(); }";
        if (str_contains($contenido, $s)) {
            $contenido = str_replace($s, $s . "\n        if (sectionId === 'alertas') { sincronizarAlertas(); }", $contenido);
        }
    }
    guardar('public/views/main.html', $contenido);

    // Arreglar solo encabezados PHP contaminados con BOM o líneas en blanco.
    foreach ([
        'src/Controllers/InventoryAlertController.php',
        'src/Controllers/ProductController.php'
    ] as $path) {
        $target = $raiz . '/' . $path;
        if (!is_file($target)) continue;
        $actual = file_get_contents($target);
        $limpio = preg_replace('/\A(?:\xEF\xBB\xBF)?\s*(?=<\?php)/', '', $actual);
        if ($actual !== $limpio) {
            respaldar($path);
            guardar($path, $limpio);
        }
    }

    $ignorar = $raiz . '/.gitignore';
    $ignorado = file_exists($ignorar) ? file_get_contents($ignorar) : '';
    $reglas = ['vendor/', '.respaldo_alertas_*/', 'probar_mongo.php', 'probar_alertas.php'];
    $nuevo = $ignorado;
    foreach ($reglas as $regla) {
        if (!preg_match('~(?:^|\R)' . preg_quote($regla, '~') . '(?:\R|$)~', $nuevo)) {
            $nuevo .= (str_ends_with($nuevo, "\n") || $nuevo === '' ? '' : "\n") . $regla . "\n";
        }
    }
    if ($nuevo !== $ignorado) {
        respaldar('.gitignore');
        guardar('.gitignore', $nuevo);
    }

    echo "\n¡INTEGRACIÓN COMPLETADA!\n";
    echo "Respaldo de archivos anteriores: " . basename($respaldo) . "\n";
    echo "Vista local: http://127.0.0.1:8080/views/alertas_demo.html\n";
    echo "La vista normal también queda integrada en public/views/main.html.\n";
    if (!is_file($raiz . '/vendor/autoload.php')) {
        echo "AVISO: falta vendor/autoload.php; instala dependencias con Composer.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\nRevisa el respaldo: {$respaldo}\n");
    exit(1);
}
