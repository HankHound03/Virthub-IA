<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\DatabaseUserStore;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:import-json {path?}', function (DatabaseUserStore $users) {
    $count = $users->importLegacyJson($this->argument('path'));
    $this->info("Usuarios importados o actualizados: {$count}");
})->purpose('Importa los usuarios existentes desde storage/app/data/users.json a la base de datos');
