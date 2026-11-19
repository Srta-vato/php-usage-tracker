<?php
/**
 * reporte_uso.php - Resume los logs de registrar_uso.php y lista archivos sin uso.
 *
 * Línea de comandos:  php reporte_uso.php
 * Navegador:          reporte_uso.php?token=TU_TOKEN   (requiere 'token_reporte' en la config)
 *
 * Bórralo del servidor cuando termines la auditoría.
 */

define('REGISTRO_USO_SOLO_FUNCIONES', true);
require_once dirname(__FILE__) . '/registrar_uso.php';

$cfg   = registro_uso_config();
$esCli = (php_sapi_name() === 'cli');

if (!$esCli) {
    $ok = $cfg['token_reporte'] !== ''
        && isset($_GET['token'])
        && registro_uso_iguales($cfg['token_reporte'], (string) $_GET['token']);
    if (!$ok) {
        header('HTTP/1.1 403 Forbidden');
        exit('Acceso denegado.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
}

$dir = registro_uso_dir();
if ($dir === null) {
    exit("No se encontró la carpeta de logs.\n");
}

// 1) Archivos ejecutados directamente (desde los CSV)
$directos = array();
$primera  = null;
$ultima   = null;
$csvs = glob($dir . '/uso_*.csv');
if (!$csvs) {
    $csvs = array();
}
foreach ($csvs as $csv) {
    $h = @fopen($csv, 'r');
    if (!$h) {
        continue;
    }
    while (($r = fgetcsv($h, 0, ',')) !== false) {
        if (count($r) < 2) {
            continue;
        }
        list($fecha, $archivo) = array($r[0], $r[1]);
        if (!isset($directos[$archivo])) {
            $directos[$archivo] = array('n' => 0, 'ultimo' => '');
        }
        $directos[$archivo]['n']++;
        if ($fecha > $directos[$archivo]['ultimo']) {
            $directos[$archivo]['ultimo'] = $fecha;
        }
        if ($primera === null || $fecha < $primera) {
            $primera = $fecha;
        }
        if ($ultima === null || $fecha > $ultima) {
            $ultima = $fecha;
        }
    }
    fclose($h);
}

// 2) Archivos cargados con include/require
$incluidos = array();
$idx = $dir . '/archivos_vistos.txt';
if (is_file($idx)) {
    $lineas = file($idx, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lineas)) {
        $incluidos = array_flip($lineas);
    }
}

// 3) Todos los .php del proyecto
function registro_uso_listar($carpeta, $raiz, $excluir, &$salida)
{
    $items = @scandir($carpeta);
    if (!$items) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $ruta = $carpeta . '/' . $item;
        if (is_dir($ruta)) {
            if (!is_link($ruta) && !in_array($item, $excluir, true)) {
                registro_uso_listar($ruta, $raiz, $excluir, $salida);
            }
        } elseif (preg_match('/\.php$/i', $item)) {
            $salida[] = substr(str_replace('\\', '/', $ruta), strlen(rtrim(str_replace('\\', '/', $raiz), '/')) + 1);
        }
    }
}
$todos = array();
registro_uso_listar($cfg['raiz'], $cfg['raiz'], $cfg['excluir_dirs'], $todos);

$sinUso = array();
$soloInclude = array();
foreach ($todos as $f) {
    if (in_array(basename($f), $cfg['ignorar'], true)) {
        continue;
    }
    if (isset($directos[$f])) {
        continue;
    }
    if (isset($incluidos[$f])) {
        $soloInclude[] = $f;
    } else {
        $sinUso[] = $f;
    }
}
sort($sinUso);
sort($soloInclude);
uasort($directos, function ($a, $b) {
    return $b['n'] - $a['n'];
});

// ---- Salida ----
echo "REPORTE DE USO DE ARCHIVOS PHP\n";
echo "==============================\n";
echo 'Raíz analizada : ' . $cfg['raiz'] . "\n";
echo 'Periodo de logs: ' . ($primera !== null ? "$primera  ->  $ultima" : 'sin datos todavía') . "\n";
echo 'Archivos .php  : ' . count($todos) . "\n\n";

echo '1) EJECUTADOS DIRECTAMENTE: ' . count($directos) . "\n";
foreach ($directos as $f => $d) {
    printf("   %6d usos | último: %s | %s\n", $d['n'], $d['ultimo'], $f);
}

echo "\n2) SOLO CARGADOS POR OTROS (include/require): " . count($soloInclude) . "\n";
foreach ($soloInclude as $f) {
    echo "   $f\n";
}

echo "\n3) SIN USO REGISTRADO: " . count($sinUso) . "\n";
foreach ($sinUso as $f) {
    echo "   $f\n";
}

echo "\nIMPORTANTE: 'sin uso' significa que no se vio en el periodo observado.\n";
echo "Los procesos anuales/mensuales, cron en CLI o includes dinámicos pueden no aparecer.\n";
echo "No borres nada sin respaldo y sin observar un periodo suficiente.\n";
