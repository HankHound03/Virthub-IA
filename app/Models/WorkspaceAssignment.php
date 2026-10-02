<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asigna un contenedor Webtop a un usuario.
 *
 * La tabla tiene un indice unico sobre user_id: es la garantia a nivel de base
 * de datos de que un usuario no puede tener dos escritorios a la vez, y de que
 * nadie comparte el de otro.
 */
class WorkspaceAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'webtop_container_id',
        'status',
        'assigned_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RELEASED = 'released';

    /** @return BelongsTo<WebtopContainer, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(WebtopContainer::class, 'webtop_container_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
