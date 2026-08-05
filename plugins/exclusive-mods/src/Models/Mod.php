<?php

namespace Exclusive\Mods\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A catalog entry for a single mod (Steam Workshop item or otherwise), scoped to
 * a driver since Workshop IDs aren't unique across different games.
 */
class Mod extends Model
{
    protected $table = 'exclusive_mods_catalog';

    protected $fillable = [
        'driver',
        'workshop_id',
        'name',
        'description',
        'author',
        'preview_image_url',
        'file_size',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'exclusive_mods_server_mods')
            ->withPivot(['load_order', 'enabled', 'installed_version', 'installed_at', 'status'])
            ->withTimestamps();
    }
}
