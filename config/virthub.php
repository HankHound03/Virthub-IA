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
    | Indices soportados: 0 y 2..7 (ct1 esta reservado). Cada entrada define el
    | IDENTIFICADOR del servicio en docker-compose (no una URL publica): los
    | escritorios se sirven por el dominio principal, porque un subdominio propio
    | permitia saltarse la comprobacion de acceso, ya que la cookie de sesion
    | viajaba alli tambien y autenticaba la peticion sola.
    |
    | La asignacion efectiva por usuario la resuelve App\Support\ContainerResolver.
    |
    */

    'containers' => [
        // Solo los escritorios que existen como servicio en docker-compose.yml.
        //
        // Cada indice debe tener su servicio `ct<n>` y su bloque en
        // docker/Caddyfile. Si se declara uno que no existe, el reparto puede
        // mandar a un usuario a un escritorio que no responde (404 en el iframe).
        //
        // Al anadir uno: crea el servicio, su bloque en el Caddyfile, define
        // CONTAINER_CT<n> en el .env y anade el indice aqui. Despues ejecuta
        // `php artisan virthub:sync-containers --prune`.
        // PELIGRO: no usar array_filter() a secas sobre los indices. El indice 0
        // (el escritorio de administradores) es falsy en PHP y array_filter lo
        // descartaria sin avisar, dejando a los administradores sin escritorio.
        'indices' => array_values(array_map(
            'intval',
            array_filter(
                explode(',', (string) env('CONTAINER_INDICES', '0,2,3')),
                static fn (string $v): bool => trim($v) !== ''
            )
        )),

        /*
        | Capacidad y limites de cada contenedor.
        |
        | Cada usuario recibe un escritorio EXCLUSIVO, guardado en la base de
        | datos, de modo que nadie comparte el de otro. Estos valores deciden
        | cuantos usuarios caben antes de que la asignacion se rechace.
        |
        | instances_per_container: cuantos escritorios puede atender un
        | contenedor. Ponlo a 1 para aislamiento total (un contenedor por
        | persona); subelo solo si el contenedor esta preparado para varios.
        |
        | cpu_limit y memory_limit_mb se guardan como metadatos de la maquina
        | para futuras cuotas. null significa sin limite declarado.
        */

        'instances_per_container' => (int) env('CONTAINER_INSTANCES', 1),
        'cpu_limit' => env('CONTAINER_CPU_LIMIT') === null ? null : (int) env('CONTAINER_CPU_LIMIT'),
        'memory_limit_mb' => env('CONTAINER_MEMORY_MB') === null ? null : (int) env('CONTAINER_MEMORY_MB'),

        // Identificador de cada escritorio. Se deriva de 'indices' para que no
        // puedan desincronizarse: si un indice no tiene servicio en el compose,
        // no debe aparecer aqui.
        //
        // El valor es el nombre del servicio (ct0, ct2...), no una URL publica:
        // los escritorios se sirven por el dominio principal, porque un
        // subdominio propio permitia saltarse la comprobacion de acceso (la
        // cookie de sesion viajaba alli tambien y autenticaba la peticion sola).
        'urls' => (static function (): array {
            // Mismo cuidado que arriba: array_filter sin criterio descartaria el
            // indice 0 por ser falsy.
            $indices = array_map(
                'intval',
                array_filter(
                    explode(',', (string) env('CONTAINER_INDICES', '0,2,3')),
                    static fn (string $v): bool => trim($v) !== ''
                )
            );

            $urls = [];

            foreach ($indices as $index) {
                $urls[$index] = (string) env('CONTAINER_CT' . $index, 'ct' . $index);
            }

            return $urls;
        })(),

        // Ruta del dominio principal por la que se sirve el escritorio.
        // Es la unica puerta: no hay subdominios de escritorio.
        'desktop_path' => (string) env('CONTAINER_DESKTOP_PATH', '/escritorio/'),

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
