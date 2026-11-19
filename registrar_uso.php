<?php
/**
 * registrar_uso.php - Registra qué archivos PHP se usan realmente en tu sitio.
 *
 * Pensado para auditar proyectos grandes y detectar archivos que nunca se ejecutan.
 * Se activa con auto_prepend_file (ver README.md). Compatible con PHP 5.3+.
 *
 * Por defecto NO guarda IPs, usuarios ni parámetros de la URL (privacidad primero).
 * Todo se ajusta en registrar_uso.config.php (copia de registrar_uso.config.example.php).
 */

if (!function_exists('registro_uso_config')) {

    /** Configuración final = valores por defecto + registrar_uso.config.php (si existe). */
    function registro_uso_config()
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        $cfg = array(
            // Carpeta de logs. Vacío = ./logs_uso junto a este archivo. Ideal: fuera del webroot.
            'dir_logs'            => '',
            // Carpeta raíz del proyecto. Vacío = DOCUMENT_ROOT (o la carpeta de este archivo).
            'raiz'                => '',
            // Archivos que no se registran (por nombre).
            'ignorar'             => array('registrar_uso.php', 'reporte_uso.php', 'registrar_uso.config.php'),
            // Carpetas que el reporte no escanea al buscar archivos sin uso.
            'excluir_dirs'        => array('vendor', 'node_modules', '.git', 'logs_uso'),
            // No registrar ejecuciones por línea de comandos (cron, scripts).
            'ignorar_cli'         => true,
            // Registrar también los archivos cargados con include/require (muy recomendado).
            'registrar_incluidos' => true,
            // IP: 'none' (no guardar), 'hash' (anonimizada con sal) o 'full' (completa).
            'ip'                  => 'none',
            // Claves de $_SESSION a registrar, ej: array('usuario'). Vacío = ninguna.
            'claves_sesion'       => array(),
            // Query string: 'none', 'claves' (solo nombres de parámetros) o 'completo'.
            'query'               => 'none',
            // Sal para anonimizar IPs. Cámbiala por un texto propio.
            'sal'                 => 'cambia-esta-sal',
            // Token para ver el reporte desde el navegador. Vacío = solo por línea de comandos.
            'token_reporte'       => '',
        );

        $archivo = dirname(__FILE__) . '/registrar_uso.config.php';
        if (is_file($archivo)) {
            $personal = include $archivo;
            if (is_array($personal)) {
                $cfg = array_merge($cfg, $personal);
            }
        }

        if ($cfg['raiz'] === '') {
            $cfg['raiz'] = (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '')
                ? $_SERVER['DOCUMENT_ROOT']
                : dirname(__FILE__);
        }
        $real = realpath($cfg['raiz']);
        if ($real !== false) {
            $cfg['raiz'] = $real;
        }
        return $cfg;
    }

    /** Devuelve la carpeta de logs (la crea y protege si hace falta) o null si no se pudo. */
    function registro_uso_dir()
    {
        static $dir = false;
        if ($dir !== false) {
            return $dir;
        }

        $cfg = registro_uso_config();
        $candidatos = array();
        if ($cfg['dir_logs'] !== '') {
            $candidatos[] = $cfg['dir_logs'];
        }
        $candidatos[] = dirname(__FILE__) . '/logs_uso';
        $candidatos[] = sys_get_temp_dir() . '/registrar_uso_logs'; // último recurso

        $dir = null;
        foreach ($candidatos as $c) {
            if (!is_dir($c)) {
                @mkdir($c, 0755, true);
            }
            if (is_dir($c) && is_writable($c)) {
                // Bloquear acceso web (Apache 2.4 y 2.2). En nginx hay que bloquearla en la config.
                $ht = $c . '/.htaccess';
                if (!file_exists($ht)) {
                    @file_put_contents($ht,
                        "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
                        "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
                    );
                }
                if (!file_exists($c . '/index.html')) {
                    @file_put_contents($c . '/index.html', '');
                }
                $dir = $c;
                break;
            }
        }
        if ($dir === null) {
            error_log('[registrar_uso] No se pudo crear/escribir ninguna carpeta de logs. Probadas: ' . implode(', ', $candidatos));
        }
        return $dir;
    }

    /** Ruta relativa a la raíz del proyecto (con "/"), o null si el archivo está fuera de ella. */
    function registro_uso_relativa($ruta)
    {
        $cfg  = registro_uso_config();
        $real = realpath($ruta);
        if ($real === false) {
            return null;
        }
        $real = str_replace('\\', '/', $real);
        $raiz = rtrim(str_replace('\\', '/', $cfg['raiz']), '/');
        if (strpos($real, $raiz . '/') !== 0) {
            return null;
        }
        return substr($real, strlen($raiz) + 1);
    }

    /** Comparación de textos en tiempo constante (con respaldo para PHP < 5.6). */
    function registro_uso_iguales($a, $b)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $r = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $r |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $r === 0;
    }

    /** Se ejecuta al final de cada petición (también con exit() o error fatal). */
    function registro_uso_registrar()
    {
        $cfg = registro_uso_config();

        if ($cfg['ignorar_cli'] && php_sapi_name() === 'cli') {
            return;
        }
        $dir = registro_uso_dir();
        if ($dir === null) {
            return;
        }

        $entrada = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
        if ($entrada === '' || in_array(basename($entrada), $cfg['ignorar'], true)) {
            return;
        }
        $rel = registro_uso_relativa($entrada);
        if ($rel === null) {
            return;
        }

        // IP
        $ip = '';
        if ($cfg['ip'] !== 'none' && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = ($cfg['ip'] === 'hash')
                ? substr(sha1($cfg['sal'] . $_SERVER['REMOTE_ADDR']), 0, 12)
                : $_SERVER['REMOTE_ADDR'];
        }

        // Claves de sesión elegidas (si la sesión está iniciada)
        $sesion = array();
        if (isset($_SESSION) && is_array($_SESSION)) {
            foreach ($cfg['claves_sesion'] as $clave) {
                if (isset($_SESSION[$clave]) && is_scalar($_SESSION[$clave])) {
                    $sesion[] = $clave . '=' . $_SESSION[$clave];
                }
            }
        }

        // Query string
        $query = '';
        $qs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        if ($cfg['query'] === 'completo') {
            $query = $qs;
        } elseif ($cfg['query'] === 'claves' && $qs !== '') {
            $params = array();
            parse_str($qs, $params);
            $query = implode(',', array_keys($params));
        }

        $limpiar = function ($v) {
            return str_replace(array("\n", "\r"), ' ', (string) $v);
        };

        $campos = array(
            date('Y-m-d H:i:s'),
            $limpiar($rel),
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
            $limpiar($ip),
            $limpiar(implode(';', $sesion)),
            $limpiar($query),
        );

        // Un CSV por mes
        $archivoLog = $dir . '/uso_' . date('Y_m') . '.csv';
        $fp = @fopen($archivoLog, 'a');
        if (!$fp) {
            error_log('[registrar_uso] No se pudo abrir ' . $archivoLog);
            return;
        }
        if (flock($fp, LOCK_EX)) {
            fputcsv($fp, $campos, ',');
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        // Archivos cargados con include/require: se anota cada archivo la primera vez que se ve.
        if ($cfg['registrar_incluidos']) {
            $idx    = $dir . '/archivos_vistos.txt';
            $vistos = array();
            if (is_file($idx)) {
                $lineas = @file($idx, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lineas)) {
                    $vistos = array_flip($lineas);
                }
            }
            $nuevos = array();
            foreach (get_included_files() as $f) {
                $r = registro_uso_relativa($f);
                if ($r === null || in_array(basename($r), $cfg['ignorar'], true)) {
                    continue;
                }
                if (!isset($vistos[$r])) {
                    $nuevos[$r] = true;
                }
            }
            if ($nuevos) {
                @file_put_contents($idx, implode("\n", array_keys($nuevos)) . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }
}

// reporte_uso.php define esta constante para cargar solo las funciones, sin registrar nada.
if (!defined('REGISTRO_USO_SOLO_FUNCIONES') && !defined('REGISTRO_USO_ACTIVO')) {
    define('REGISTRO_USO_ACTIVO', true);
    register_shutdown_function('registro_uso_registrar');
}
