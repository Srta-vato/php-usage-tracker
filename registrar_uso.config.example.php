<?php
/**
 * Copia este archivo como registrar_uso.config.php y ajusta lo que necesites.
 * (registrar_uso.config.php está en .gitignore para que no subas datos propios.)
 * Solo escribe las opciones que quieras cambiar.
 */
return array(
    // Carpeta de logs, idealmente FUERA del webroot. Ej: '/home/usuario/logs_uso'
    // 'dir_logs' => '',

    // Raíz del proyecto (necesaria si usas el reporte por línea de comandos).
    // 'raiz' => '/home/usuario/public_html',

    // Guardar IP: 'none' | 'hash' | 'full'
    // 'ip' => 'hash',
    // 'sal' => 'pon-aqui-un-texto-largo-y-aleatorio',

    // Claves de $_SESSION a registrar (solo si realmente las necesitas).
    // 'claves_sesion' => array('usuario'),

    // Parámetros de URL: 'none' | 'claves' | 'completo'
    // 'query' => 'claves',

    // Token para abrir el reporte desde el navegador (déjalo vacío para solo CLI).
    // 'token_reporte' => 'un-token-largo-y-aleatorio',
);
