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
    | Version de la aplicacion
    |--------------------------------------------------------------------------
    |
    | Se muestra en el pie de todas las paginas. Vive aqui y no escrita en cada
    | vista para que al subirla no haya que tocar nueve archivos y no acaben
    | quedando numeros distintos en cada uno.
    |
    */

    'version' => env('VIRTHUB_VERSION', 'VirtHub Codename: Redmond'),

    /*
    |--------------------------------------------------------------------------
    | Temas de diseno (solo pruebas)
    |--------------------------------------------------------------------------
    |
    | Muestra un selector flotante con los estilos historicos de Windows (XP,
    | Vista, 7, 8 y 10) para comparar disenos.
    |
    | Desactivado por defecto: se enciende con VIRTHUB_TEMAS=true, y solo lo hace
    | docker-compose.test.yml. En produccion no aparece nada.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Noticias
    |--------------------------------------------------------------------------
    |
    | Panel de noticias del inicio (Linux y ciberseguridad) y sus rutas
    | /linux-news y /cyber-news.
    |
    | Activado por defecto. Se apaga con VIRTHUB_NOTICIAS=false, que es lo que hace
    | docker-compose.test.yml para el entorno de pruebas.
    |
    */

    'noticias' => [
        'enabled' => filter_var(env('VIRTHUB_NOTICIAS', true), FILTER_VALIDATE_BOOL),
    ],

    'temas' => [
        'enabled' => filter_var(env('VIRTHUB_TEMAS', false), FILTER_VALIDATE_BOOL),
        'activo' => (string) env('VIRTHUB_TEMA_INICIAL', ''),

        /*
         * Catalogo de estilos disponibles.
         *
         * Cada epoca (era) tiene sus esquemas de color (variantes). Los colores
         * 'a' y 'b' son solo para el degradado de la muestra en el carrusel; el
         * aspecto real lo pone public/temas.css.
         *
         * Anadir un esquema es anadir una entrada aqui y su bloque en el CSS.
         */
        'catalogo' => [
            'xp' => [
                'nombre' => 'Windows XP',
                'imagen' => '/pics/temas/winxp.jpg',
                'variantes' => [
                    'luna' => ['nombre' => 'Luna azul', 'a' => '#0058e6', 'b' => '#3a93ff'],
                    'olive' => ['nombre' => 'Oliva', 'a' => '#6d7f3c', 'b' => '#9aad5a'],
                    'silver' => ['nombre' => 'Plata', 'a' => '#9a9a9a', 'b' => '#dedede'],
                    'royale' => ['nombre' => 'Royale', 'a' => '#0a1f3d', 'b' => '#2f6ea8'],
                    'embedded' => ['nombre' => 'Embedded', 'a' => '#232f3a', 'b' => '#44586b'],
                    'classic' => ['nombre' => 'Clasico', 'a' => '#000080', 'b' => '#4a4a9a'],
                ],
            ],
            'vista' => [
                'nombre' => 'Windows Vista',
                'imagen' => '/pics/temas/vista.jpg',
                'variantes' => [
                    'aero' => ['nombre' => 'Aero', 'a' => '#1b4a63', 'b' => '#7ec8e3'],
                    'graphite' => ['nombre' => 'Grafito', 'a' => '#22262b', 'b' => '#6b7480'],
                    'teal' => ['nombre' => 'Turquesa', 'a' => '#0b4f4a', 'b' => '#3fd0c0'],
                    'red' => ['nombre' => 'Rojo', 'a' => '#4a1218', 'b' => '#e0566a'],
                    'green' => ['nombre' => 'Verde', 'a' => '#1a3d1a', 'b' => '#69c96b'],
                    'violet' => ['nombre' => 'Violeta', 'a' => '#2b1745', 'b' => '#a97cf0'],
                ],
            ],
            'win7' => [
                'nombre' => 'Windows 7',
                'imagen' => '/pics/temas/win7.jpg',
                'variantes' => [
                    'aero' => ['nombre' => 'Aero', 'a' => '#bfe3f7', 'b' => '#1f4a68'],
                    'architecture' => ['nombre' => 'Arquitectura', 'a' => '#d8d2c8', 'b' => '#3a3a3a'],
                    'nature' => ['nombre' => 'Naturaleza', 'a' => '#d6f0c8', 'b' => '#2f6b2f'],
                    'scenes' => ['nombre' => 'Escenas', 'a' => '#c8eef0', 'b' => '#0f5a63'],
                    'characters' => ['nombre' => 'Personajes', 'a' => '#f7d0e2', 'b' => '#8a2b5c'],
                    'landscapes' => ['nombre' => 'Paisajes', 'a' => '#f8e4c0', 'b' => '#8a5218'],
                ],
            ],
            'win8' => [
                'nombre' => 'Windows 8',
                'imagen' => '/pics/temas/win8.jpg',
                'variantes' => [
                    'blue' => ['nombre' => 'Azul', 'a' => '#00a4ef', 'b' => '#0072c6'],
                    'purple' => ['nombre' => 'Purpura', 'a' => '#7c4dff', 'b' => '#4527a0'],
                    'green' => ['nombre' => 'Verde', 'a' => '#00ab6c', 'b' => '#00724a'],
                    'orange' => ['nombre' => 'Naranja', 'a' => '#f7630c', 'b' => '#b84400'],
                    'red' => ['nombre' => 'Rojo', 'a' => '#e81123', 'b' => '#a00010'],
                    'teal' => ['nombre' => 'Turquesa', 'a' => '#00b7c3', 'b' => '#00777f'],
                ],
            ],
            'win10' => [
                'nombre' => 'Windows 10',
                'imagen' => '/pics/temas/win10.jpg',
                'variantes' => [
                    'azul-claro' => ['nombre' => 'Claro azul', 'a' => '#f3f3f3', 'b' => '#0078d7'],
                    'verde-claro' => ['nombre' => 'Claro verde', 'a' => '#f3f3f3', 'b' => '#107c10'],
                    'grafito-claro' => ['nombre' => 'Claro grafito', 'a' => '#f3f3f3', 'b' => '#5d5d5d'],
                    'azul-oscuro' => ['nombre' => 'Oscuro azul', 'a' => '#202020', 'b' => '#4cc2ff'],
                    'purpura-oscuro' => ['nombre' => 'Oscuro purpura', 'a' => '#202020', 'b' => '#b47cf0'],
                    'ambar-oscuro' => ['nombre' => 'Oscuro ambar', 'a' => '#202020', 'b' => '#ffb900'],
                ],
            ],
        ],
    ],

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

        // URL publica de cada escritorio.
        //
        // Cada uno tiene su propio subdominio, que el tunel de Cloudflare enruta
        // a Caddy. El valor se puede sobreescribir con CONTAINER_CT<n> en el
        // .env (util si el dominio cambia).
        //
        // AVISO: la cookie de sesion vive en el dominio principal, asi que
        // viaja tambien a estos subdominios. Sin sesion no se entra (Caddy
        // responde 403), pero el propio usuario con sesion abierta si puede
        // abrirlos directamente.
        'urls' => (static function (): array {
            // PELIGRO: no usar array_filter sin criterio. El indice 0 (el
            // escritorio de administradores) es falsy en PHP y se perderia sin
            // aviso, dejando a los administradores sin escritorio.
            $indices = array_map(
                'intval',
                array_filter(
                    explode(',', (string) env('CONTAINER_INDICES', '0,2,3')),
                    static fn (string $v): bool => trim($v) !== ''
                )
            );

            $base = (string) env('CONTAINER_DOMAIN', 'virthub.dpdns.org');

            $urls = [];

            foreach ($indices as $index) {
                $urls[$index] = (string) env(
                    'CONTAINER_CT' . $index,
                    'https://ct' . $index . '.' . $base . '/'
                );
            }

            return $urls;
        })(),

        // Host y puerto internos de los escritorios que NO viven en este Docker
        // (por ejemplo los del equipo 2). Formato:
        //
        //   CONTAINER_HOSTS=8=192.168.1.100:3000,9=192.168.1.100:3100
        //
        // Los usa el panel de estado, que sondea cada escritorio por la red local
        // en lugar de por su URL publica (que responde 403 sin sesion y haria
        // aparecer como caidos escritorios que estan perfectamente).
        //
        // Antes habia que escribirlos a mano en la base de datos, asi que se
        // perdian al recrear el entorno y no quedaba rastro de donde salian.
        'hosts' => (static function (): array {
            $mapa = [];

            foreach (explode(',', (string) env('CONTAINER_HOSTS', '')) as $par) {
                $par = trim($par);

                if ($par === '' || ! str_contains($par, '=')) {
                    continue;
                }

                [$indice, $destino] = explode('=', $par, 2);
                $indice = trim($indice);
                $destino = trim($destino);

                if (! is_numeric($indice) || ! str_contains($destino, ':')) {
                    continue;
                }

                [$host, $puerto] = explode(':', $destino, 2);

                if (trim($host) === '' || ! is_numeric(trim($puerto))) {
                    continue;
                }

                $mapa[(int) $indice] = [
                    'host' => trim($host),
                    'port' => (int) trim($puerto),
                ];
            }

            return $mapa;
        })(),

        // Ruta del dominio principal por la que se sirve el escritorio.
        // Es la unica puerta: no hay subdominios de escritorio.
        'desktop_path' => (string) env('CONTAINER_DESKTOP_PATH', '/escritorio/'),

        // Puerto interno de Webtop. El panel de estado sondea cada escritorio por
        // la red de Docker (http://ct0:3000) en lugar de por su URL publica: la
        // publica pasa por Caddy y responde 403 sin sesion, lo que hacia aparecer
        // como caidos escritorios que estaban perfectamente.
        'internal_port' => (int) env('CONTAINER_INTERNAL_PORT', 3000),

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
            // 'svg' fuera: un SVG puede contener scripts y se serviria desde nuestro
            // dominio, lo que permitiria inyectarlos (XSS). Los iconos de la
            // aplicacion no pasan por esta lista.
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'ico', 'heic', 'tiff',
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
            // Codigo y texto plano.
            //
            // OJO: aqui NO van 'php', 'phtml', 'phar' ni nada que el servidor web
            // pueda ejecutar: los adjuntos se publican bajo el docroot y un .php
            // subido se ejecutaria en el servidor. Tampoco 'html', 'htm' ni 'svg',
            // que son contenido activo y permitirian inyectar scripts desde el
            // propio dominio.
            'js', 'css', 'py', 'java', 'c', 'cpp', 'h', 'sh',
            'sql', 'rb', 'go', 'rs', 'ts', 'jsx', 'tsx', 'vue', 'lua',
            // Modelos 3D y diseno
            'stl', 'obj', 'blend', 'psd', 'ai', 'fig', 'sketch',
        ],

        // Limite de peticiones por minuto para los trozos: 8 MB por trozo, un
        // archivo de 5 GB son ~640 trozos, asi que el limite debe ser holgado.
        'chunk_rate_limit' => (int) env('UPLOAD_CHUNK_RATE_LIMIT', 2400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Antivirus de los adjuntos
    |--------------------------------------------------------------------------
    |
    | ClamAV local (servicio `clamav` del compose). Se consulta por socket TCP
    | antes de guardar cada archivo: foro, subida por trozos y avatares.
    |
    | Se hace en local y no con un servicio externo por dos razones: los archivos
    | de los usuarios no salen del servidor, y no hay limite de tamano ni de
    | peticiones (los adjuntos pueden llegar a 5 GB por trozos).
    |
    | 'fail_open': que hacer si el antivirus no responde.
    |   true  -> se permite la subida y se registra un aviso. Asi el foro no se
    |            queda sin poder subir nada mientras ClamAV reinicia.
    |   false -> se rechaza la subida. Mas estricto, pero un fallo del antivirus
    |            bloquea todas las subidas.
    |
    */

    'antivirus' => [
        'enabled' => (bool) env('CLAMAV_ENABLED', true),
        'host' => (string) env('CLAMAV_HOST', 'clamav'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 60),
        'fail_open' => (bool) env('CLAMAV_FAIL_OPEN', true),
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
        'cache_seconds' => (int) env('STATUS_CACHE_SECONDS', 60),
        'probe_timeout_seconds' => (int) env('STATUS_PROBE_TIMEOUT', 2),

            // Los escritorios de otra maquina necesitan mas margen: cruzar la red
            // local es mas lento que la red interna de Docker. Con el mismo
            // margen que los locales se marcaban caidos estando activos.
            'probe_timeout_remote_seconds' => (int) env('STATUS_PROBE_TIMEOUT_REMOTE', 8),

            // Cuanto se recuerda un escritorio caido antes de volver a probarlo.
            // Su nombre no resuelve y cada intento cuesta unos 4 s de espera.
            'probe_down_seconds' => (int) env('STATUS_PROBE_DOWN_SECONDS', 180),
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
