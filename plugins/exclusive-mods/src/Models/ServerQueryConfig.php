<?php

namespace Exclusive\Mods\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-server REST query credentials for the Players Online feature. Only needed
 * as a fallback when the installed egg doesn't already expose these via its own
 * EggVariables.
 */
class ServerQueryConfig extends Model
{
    protected $table = 'exclusive_mods_server_query_configs';

    protected $fillable = ['server_id', 'rest_api_port', 'rest_api_password'];

    protected function casts(): array
    {
        return [
            'rest_api_port' => 'integer',
            'rest_api_password' => 'encrypted',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
