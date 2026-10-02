<?php

/*
|--------------------------------------------------------------------------
| Configuracion de Virthub
|--------------------------------------------------------------------------
|
| Todo valor que provenga del entorno vive aqui. Las clases de la aplicacion
| deben usar config('virthub.*') y nunca env(), porque env() devuelve null
| cuando la configuracion esta cacheada (php artisan config:cache).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Contenedores Webtop
    |--------------------------------------------------------------------------
    |
    | Indices soportados: 0 y 2..7 (ct1 esta reservado). Cada entrada define la
    | URL publica del contenedor y su etiqueta. La asignacion efectiva por
    | usuario la resuelve App\Support\ContainerResolver.
    |
    */

    'containers' => [
        'indices' => [0, 2, 3, 4, 5, 6, 7],

        'urls' => array_combine(
            [0, 2, 3, 4, 5, 6, 7],
            array_map(
                static fn (int $index): string => (string) env(
                    'CONTAINER_CT' . $index,
                    'https://ct' . $index . '.virthub.dpdns.org/'
                ),
                [0, 2, 3, 4, 5, 6, 7]
            )
        ),

        // Contenedor que recibe a los usuarios invitados.
        'guest_index' => 7,

        // Usuarios que van al contenedor exclusivo (ct0).
        'admin_users' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CONTAINER_ADMIN_USERS', 'admin,hankhound03'))
        ))),

        // Rango de reparto automatico para el resto de usuarios (inclusive).
        'pool_start' => 2,
        'pool_end' => 6,

        // Contenedor legado consultado por el panel de estado.
        'webtop_url' => (string) env('WEBTOP_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama (IA local)
    |--------------------------------------------------------------------------
    */

    'ollama' => [
        'base_url' => trim((string) env('OLLAMA_BASE_URL', '')),
        'model' => trim((string) env('OLLAMA_MODEL', 'llama3.1')),

        'system_prompt' => trim((string) env(
            'OLLAMA_SYSTEM_PROMPT',
            'Responde en español, de forma clara, breve y útil. Usa listas y saltos de línea cuando ayuden a leer mejor la respuesta.'
        )),

        'request_timeout' => (int) env('OLLAMA_REQUEST_TIMEOUT', 300),
        'connect_timeout' => (int) env('OLLAMA_CONNECT_TIMEOUT', 5),

        // Nombre del interlocutor simulado dentro del chat.
        'chat_username' => 'ollama',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuenta administradora inicial
    |--------------------------------------------------------------------------
    |
    | Solo se usa para crear el primer admin cuando el almacen esta vacio y se
    | invoca bootstrapAdminFromEnv(true). Si la clave sigue siendo la de
    | ejemplo, la creacion se rechaza en lugar de dejar una puerta abierta.
    |
    */

    'admin' => [
        'username' => (string) env('ADMIN_USERNAME', 'admin'),
        'password' => (string) env('ADMIN_PASSWORD', ''),
        'default_password' => 'ChangeMeNow123!',
    ],

    /*
    |--------------------------------------------------------------------------
    | Adjuntos y subidas
    |--------------------------------------------------------------------------
    |
    | Los archivos grandes NO se suben en una sola peticion: se trocean desde el
    | navegador y el servidor los ensambla. Asi se evita el techo de PHP, que
    | tendria que bufferizar el archivo entero en un proceso de Apache.
    |
    | chunk_bytes debe quedar por debajo de post_max_size de PHP (ver
    | docker/php.ini) para que cada trozo quepa con holgura.
    |
    */

    'uploads' => [
        // Tamaño maximo por archivo. 5 GB.
        'max_file_bytes' => (int) env('UPLOAD_MAX_FILE_BYTES', 5 * 1024 ** 3),

        // Tamaño de cada trozo. 8 MB deja margen sobrado bajo post_max_size=40M.
        'chunk_bytes' => (int) env('UPLOAD_CHUNK_BYTES', 8 * 1024 ** 2),

        // Cuantos archivos se pueden adjuntar a una publicacion.
        'max_files_per_post' => (int) env('UPLOAD_MAX_FILES', 10),

        // Horas tras las cuales una subida incompleta se considera abandonada.
        'session_ttl_hours' => (int) env('UPLOAD_SESSION_TTL_HOURS', 24),

        // Extensiones permitidas (la comprobacion real tambien valida el MIME).
        // Es una lista de bloqueo implicita: se excluye lo que el servidor
        // ejecutaria o interpretaria, no lo que el usuario quiera guardar.
        'allowed_extensions' => [
            // Imagenes
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'ico', 'heic', 'tiff',
            // Video
            'mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v', 'mpg', 'mpeg', 'wmv', 'flv',
            // Audio
            'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'wma', 'opus',
            // Documentos
            'pdf', 'txt', 'md', 'rtf', 'csv', 'tsv', 'json', 'xml', 'yaml', 'yml',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'epub',
            // Comprimidos
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz',
            // Discos e imagenes de sistema
            'iso', 'img', 'bin', 'vhd', 'vdi', 'qcow2', 'dmg',
            // Datos y registros
            'log', 'dat', 'db', 'sqlite', 'bak', 'dump', 'torrent',
            // Codigo y texto plano
            'php', 'js', 'css', 'html', 'htm', 'py', 'java', 'c', 'cpp', 'h', 'sh',
            'sql', 'rb', 'go', 'rs', 'ts', 'jsx', 'tsx', 'vue', 'lua', 'pl',
            // Modelos 3D y diseno
            'stl', 'obj', 'blend', 'psd', 'ai', 'fig', 'sketch',
        ],

        // Limite de peticiones por minuto para los trozos: 8 MB por trozo, un
        // archivo de 5 GB son ~640 trozos, asi que el limite debe ser holgado.
        'chunk_rate_limit' => (int) env('UPLOAD_CHUNK_RATE_LIMIT', 2400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Esquema de las URLs generadas
    |--------------------------------------------------------------------------
    |
    | Cuando la aplicacion vive detras de un proxy TLS (Cloudflare, Caddy,
    | Traefik, nginx), activa esto para que asset() y route() generen enlaces
    | https aunque la peticion llegue al contenedor por http.
    |
    | Dejalo en false si sirves por http: si lo activas sin TLS delante, el
    | navegador pedira los CSS y JS por https, no podra conectarse y la pagina
    | se vera sin estilos.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Instalador
    |--------------------------------------------------------------------------
    */

    'installation' => [
        // Permitir el instalador sin clave cuando la peticion llega desde el
        // propio host. Debe ser false en produccion.
        'allow_localhost_without_key' => (bool) env('INSTALL_ALLOW_LOCALHOST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitados
    |--------------------------------------------------------------------------
    */

    'guests' => [
        'session_minutes' => (int) env('GUEST_SESSION_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presencia en el chat
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'presence_window_seconds' => (int) env('CHAT_PRESENCE_WINDOW', 90),
        'posts_per_page' => (int) env('CHAT_PAGE_SIZE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel de estado del sistema
    |--------------------------------------------------------------------------
    */

    'status' => [
        'cache_seconds' => (int) env('STATUS_CACHE_SECONDS', 15),
        'probe_timeout_seconds' => (int) env('STATUS_PROBE_TIMEOUT', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuentes RSS del panel
    |--------------------------------------------------------------------------
    */

    'feeds' => [
        'cache_seconds' => (int) env('FEED_CACHE_SECONDS', 300),
        'timeout_seconds' => (int) env('FEED_TIMEOUT_SECONDS', 8),
        'items' => 6,
        'linux' => 'https://www.phoronix.com/rss.php',
        'cyber' => 'https://feeds.feedburner.com/TheHackersNews',
    ],

];
