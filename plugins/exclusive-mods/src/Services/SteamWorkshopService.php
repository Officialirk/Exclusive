<?php

namespace Exclusive\Mods\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Thin wrapper around the Steam Web API's Workshop endpoints, following the same
 * Http:: usage style as core's SoftwareVersionService/PluginService.
 *
 * NOTE: verify these endpoint versions/params against current Steamworks docs
 * before relying on them in production - Valve iterates these over time.
 */
class SteamWorkshopService
{
    /**
     * Resolve a pasted Workshop URL or a raw numeric ID into raw item details.
     * GetPublishedFileDetails does not require an API key.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $input): array
    {
        $workshopId = $this->extractWorkshopId($input);

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/', [
                'itemcount' => 1,
                'publishedfileids[0]' => $workshopId,
            ])
            ->throw()
            ->json();

        $details = $response['response']['publishedfiledetails'][0] ?? null;

        throw_if(!$details || (int) ($details['result'] ?? 0) !== 1, new InvalidArgumentException("Workshop item {$workshopId} could not be resolved."));

        return [
            'workshop_id' => $workshopId,
            'name' => $details['title'] ?? "Workshop Item {$workshopId}",
            'description' => $details['description'] ?? null,
            'author' => $details['creator'] ?? null,
            'preview_image_url' => $details['preview_url'] ?? null,
            'file_size' => isset($details['file_size']) ? (int) $details['file_size'] : null,
            'file_url' => $details['file_url'] ?? null,
            'metadata' => $details,
        ];
    }

    /**
     * Search the Workshop for a given app. Requires a configured Steam Web API
     * key - returns an empty collection (rather than throwing) when none is
     * configured so the paste-a-URL install flow keeps working key-less.
     */
    public function search(string $query, ?int $appId = null): Collection
    {
        $apiKey = config('exclusive-mods.steam_api_key');

        if (!$apiKey) {
            return collect();
        }

        $response = Http::timeout(10)
            ->get('https://api.steampowered.com/IPublishedFileService/QueryFiles/v1/', array_filter([
                'key' => $apiKey,
                'query_type' => 1,
                'search_text' => $query,
                'appid' => $appId,
                'numperpage' => 25,
                'return_details' => true,
            ]))
            ->throw()
            ->json();

        return collect($response['response']['publishedfiledetails'] ?? []);
    }

    private function extractWorkshopId(string $input): string
    {
        if (preg_match('/^\d+$/', trim($input))) {
            return trim($input);
        }

        if (preg_match('/[?&]id=(\d+)/', $input, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException("Could not extract a Workshop ID from \"{$input}\".");
    }
}
