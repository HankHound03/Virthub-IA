<?php

namespace App\Providers;

use App\Services\DatabaseUserStore;
use App\Services\JsonUserStore;
use App\Services\UserStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La aplicacion depende de la interfaz UserStore, nunca de una
        // implementacion concreta. En produccion se usa la base de datos; la
        // suite de pruebas usa el almacen JSON por velocidad y aislamiento.
        //
        // Las clases concretas NO se enlazan entre si: asi los tests pueden
        // pedir DatabaseUserStore de forma explicita y ejercitar el codigo que
        // atiende a los usuarios reales.
        $this->app->bind(UserStore::class, $this->app->environment('testing')
            ? JsonUserStore::class
            : DatabaseUserStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login-ip', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip() ?: 'unknown');
        });

        // El acceso de invitado reserva un escritorio compartido y no exige
        // credenciales, asi que se limita con dureza por IP.
        RateLimiter::for('guest-login', function (Request $request): Limit {
            return Limit::perMinutes(10, 3)->by($request->ip() ?: 'unknown');
        });

        // Evita que una cuenta inunde el chat (y el puente hacia Ollama).
        RateLimiter::for('chat-send', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) optional($request->user())->getAuthIdentifier() ?: ($request->ip() ?: 'unknown'));
        });

        // Un archivo de 5 GB en trozos de 8 MB son ~640 peticiones, asi que el
        // limite debe ser holgado pero acotado: frena un bucle descontrolado sin
        // estorbar a una subida legitima.
        RateLimiter::for('upload-chunk', function (Request $request): Limit {
            $perMinute = max(60, (int) config('virthub.uploads.chunk_rate_limit', 2400));

            return Limit::perMinute($perMinute)->by($request->ip() ?: 'unknown');
        });

        // El proxy pregunta por cada peticion del escritorio, incluidas las de
        // WebSocket. El limite debe ser alto para no cortar una sesion normal.
        RateLimiter::for('proxy-auth', function (Request $request): Limit {
            return Limit::perMinute(6000)->by($request->ip() ?: 'unknown');
        });

        if (! app()->runningInConsole()) {
            $this->configureUrlScheme(request());
        }
    }

    /**
     * Decide el esquema de las URLs generadas.
     *
     * Antes bastaba con que APP_URL empezara por https:// para forzar https en
     * todas las URLs, y eso ocurria incluso sirviendo por http plano: el
     * navegador pedia los CSS por https contra un puerto sin TLS y la pagina se
     * quedaba sin estilos.
     *
     * Reglas ahora:
     *   - Si ya hay evidencia de https (peticion segura o cabecera de proxy),
     *     se respeta.
     *   - Si no, solo se fuerza con FORCE_HTTPS=true, que es lo correcto cuando
     *     hay un proxy TLS delante.
     */
    private function configureUrlScheme(Request $request): void
    {
        $hasSecureEvidence = $request->isSecure()
            || $request->headers->get('x-forwarded-proto') === 'https';

        if ($hasSecureEvidence || config('virthub.force_https')) {
            URL::forceScheme('https');
        }
    }
}
