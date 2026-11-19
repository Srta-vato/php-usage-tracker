# PHP Usage Tracker

Herramienta mínima para descubrir **qué archivos PHP de tu proyecto se usan realmente y cuáles no**.
Útil en sistemas antiguos o muy grandes, donde nadie sabe qué se puede limpiar.

- Un solo archivo para registrar (`registrar_uso.php`) y uno para el reporte (`reporte_uso.php`).
- Sin dependencias, compatible con **PHP 5.3+**.
- No envía nada a servidores externos: todo queda en archivos locales.
- **Privacidad por defecto:** no guarda IPs, usuarios ni parámetros de la URL a menos que lo actives.

## Cómo funciona

1. `registrar_uso.php` se ejecuta antes de cada script PHP (vía `auto_prepend_file`).
2. Al terminar la petición anota en un CSV mensual qué archivo se ejecutó.
3. Opcionalmente anota también los archivos cargados con `include`/`require`.
4. `reporte_uso.php` compara esos registros contra todos los `.php` del proyecto y muestra:
   ejecutados directamente, cargados solo por otros, y **sin uso registrado**.

## Instalación

1. Sube `registrar_uso.php` y `reporte_uso.php` a la raíz de tu sitio.
2. (Opcional) Copia `registrar_uso.config.example.php` como `registrar_uso.config.php` y ajusta.
3. Actívalo con **una** de estas opciones:

   **`.user.ini`** (hosting compartido con PHP-FPM/CGI; cPanel suele usar este):
   ```ini
   auto_prepend_file = "/ruta/absoluta/registrar_uso.php"
   ```
   **`.htaccess`** (Apache con mod_php / LiteSpeed):
   ```apache
   php_value auto_prepend_file "/ruta/absoluta/registrar_uso.php"
   ```
   **`php.ini`** si tienes acceso al servidor.

   Alternativa sin tocar configuración: agrega `require_once '/ruta/registrar_uso.php';`
   al inicio de tu archivo de entrada (solo registrará lo que pase por ahí).

4. Navega el sistema con normalidad durante un periodo **representativo** (idealmente semanas o meses).

## Ver el reporte

Por línea de comandos (recomendado):
```bash
php reporte_uso.php
```
Si no tienes SSH, define `token_reporte` en la configuración y abre
`reporte_uso.php?token=TU_TOKEN`. Elimina el reporte cuando termines.

## Configuración

| Opción | Por defecto | Qué hace |
|---|---|---|
| `dir_logs` | `./logs_uso` | Carpeta de logs (mejor fuera del webroot) |
| `raiz` | `DOCUMENT_ROOT` | Raíz del proyecto |
| `ignorar` | los archivos de la herramienta | Nombres de archivo que no se registran |
| `excluir_dirs` | `vendor`, `node_modules`, `.git`, `logs_uso` | Carpetas que el reporte no escanea |
| `ignorar_cli` | `true` | No registrar scripts de consola/cron |
| `registrar_incluidos` | `true` | Registrar también includes/requires |
| `ip` | `none` | `none`, `hash` (anonimizada) o `full` |
| `claves_sesion` | `array()` | Claves de `$_SESSION` a anotar |
| `query` | `none` | `none`, `claves` o `completo` |
| `token_reporte` | vacío | Habilita el reporte por navegador |

## Limitaciones (léelas antes de borrar nada)

- **"Sin uso" solo significa "no visto en el periodo observado".** Procesos anuales, cierres de
  periodo o tareas poco frecuentes pueden no aparecer.
- Los includes dinámicos funcionan (se registra lo realmente cargado), pero los archivos usados
  únicamente por **cron en CLI** no se ven con `ignorar_cli = true`.
- Los archivos no PHP (plantillas, JS, imágenes) no se analizan.
- Hace una pequeña lectura/escritura en disco por petición: úsalo como auditoría temporal.
- **Haz respaldo antes de eliminar cualquier archivo.**

## Seguridad y privacidad

- Guarda los logs **fuera del webroot** cuando puedas. La herramienta crea un `.htaccess` que
  bloquea el acceso en Apache; en **nginx** debes bloquear la carpeta en la configuración.
- **Nunca subas los logs** a un repositorio (ya están en `.gitignore`).
- Si activas `ip`, `claves_sesion` o `query`, estás recolectando **datos personales**.
  Infórmalo a los usuarios y cumple la normativa aplicable (p. ej. GDPR en Europa o la
  Ley 1581 de 2012 en Colombia). Con la configuración por defecto no se guarda ninguno.
- `query = completo` puede capturar tokens o documentos enviados por URL. Evítalo.
- Usa esta herramienta **solo en sistemas que administras o con autorización** de su dueño.

## Desinstalar sin tumbar el sitio

Si `auto_prepend_file` apunta a un archivo que ya no existe, **PHP da error fatal en todo el sitio**.
El orden correcto es:

1. Quita (o comenta con `;`) la línea `auto_prepend_file` del `.user.ini` / `.htaccess` / `php.ini`.
2. Espera unos 5 minutos (PHP cachea los `.user.ini`) o reinicia PHP si editaste `php.ini`.
3. Verifica que el sitio funcione.
4. Ahora sí, borra `registrar_uso.php`, `reporte_uso.php` y la carpeta de logs.

Si ya lo borraste y el sitio cayó: vuelve a subir `registrar_uso.php` y repite los pasos en orden.

## Licencia

MIT. Ver `LICENSE`.
