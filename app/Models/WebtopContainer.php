<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un contenedor Webtop disponible para asignar a usuarios.
 *
 * Corresponde a la tabla webtop_containers, que ya existia en el esquema con
 * los limites de CPU y memoria previstos desde el principio.
 */
class WebtopContainer extends Model
{
    protected $fillable = [
        'name',
        'url',
        'capacity',
        'status',
        'cpu_limit',
        'memory_limit_mb',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'cpu_limit' => 'integer',
            'memory_limit_mb' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BUSY = 'busy';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_OFFLINE = 'offline';

    /** @return HasMany<WorkspaceAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkspaceAssignment::class);
    }

    /** Asignaciones que siguen activas (sin liberar). */
    /** @return HasMany<WorkspaceAssignment, $this> */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('released_at');
    }

    public function isAvailable(): bool
    {
        return $this->status !== self::STATUS_OFFLINE;
    }
}
