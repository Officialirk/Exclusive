<?php

namespace Exclusive\Mods\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dedicated (non-pivot) model for the server<->mod install state, since jobs
 * need to mutate `status` directly through an install/update/uninstall lifecycle.
 */
class ServerMod extends Model
{
    protected $table = 'exclusive_mods_server_mods';

    public const STATUS_PENDING = 'pending';

    public const STATUS_INSTALLING = 'installing';

    public const STATUS_INSTALLED = 'installed';

    public const STATUS_UPDATING = 'updating';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'server_id',
        'mod_id',
        'load_order',
        'enabled',
        'installed_version',
        'installed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'installed_at' => 'datetime',
            'load_order' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }
}
