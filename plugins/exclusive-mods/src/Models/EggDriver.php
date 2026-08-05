<?php

namespace Exclusive\Mods\Models;

use App\Models\Egg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps an Egg to the mod-manager driver slug that supports it. Lives in this
 * plugin's own table rather than as a column on the core `eggs` table.
 */
class EggDriver extends Model
{
    protected $table = 'exclusive_mods_egg_drivers';

    protected $fillable = ['egg_id', 'driver'];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
