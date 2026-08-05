<?php

namespace Exclusive\Mods\Services\ServerQuery;

use App\Models\Server;
use Exclusive\Mods\Contracts\ServerQueryServiceInterface;
use Exclusive\Mods\Models\ServerQueryConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NOTE: endpoint path, default port, auth scheme, and response shape are a
 * flagged assumption from the plan - verify against current Palworld dedicated
 * server REST API docs (and a live response) before relying on this in
 * production.
 */
class PalworldRestQueryService implements ServerQueryServiceInterface
{
    private const DEFAULT_PORT = 8212;

    public function isSupported(Server $server): bool
    {
        return $this->resolveCredentials($server) !== null;
    }

    /** @return array<int, array{name: string, ping: int|null}> */
    public function getPlayers(Server $server): array
    {
        return cache()->remember(
            "exclusive-mods.servers.{$server->id}.players_online",
            now()->addSeconds(10),
            fn () => $this->fetchPlayers($server),
        );
    }

    /** @return array<int, array{name: string, ping: int|null}> */
    private function fetchPlayers(Server $server): array
    {
        $credentials = $this->resolveCredentials($server);

        if (!$credentials || $server->retrieveStatus()->isOffline()) {
            return [];
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->withBasicAuth('admin', $credentials['password'])
                ->get("http://{$server->allocation->ip}:{$credentials['port']}/v1/api/players")
                ->throw()
                ->json();

            return collect($response['players'] ?? [])
                ->map(fn (array $player) => [
                    'name' => $player['name'] ?? 'Unknown',
                    'ping' => isset($player['ping']) ? (int) round($player['ping']) : null,
                ])
                ->all();
        } catch (Throwable $exception) {
            Log::debug("Could not fetch Palworld player list for server {$server->id}: {$exception->getMessage()}");

            return [];
        }
    }

    /** @return array{port: int, password: string}|null */
    private function resolveCredentials(Server $server): ?array
    {
        $variables = $server->variables;
        $port = $variables->firstWhere('env_variable', 'REST_API_PORT')?->server_value;
        $password = $variables->firstWhere('env_variable', 'ADMIN_PASSWORD')?->server_value;

        if ($port && $password) {
            return ['port' => (int) $port, 'password' => $password];
        }

        $config = ServerQueryConfig::query()->where('server_id', $server->id)->first();

        if ($config?->rest_api_port && $config->rest_api_password) {
            return ['port' => $config->rest_api_port, 'password' => $config->rest_api_password];
        }

        return null;
    }
}
