<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Steam Web API key
    |--------------------------------------------------------------------------
    |
    | Optional. Only needed for searching/browsing the Steam Workshop from
    | inside the panel (via IPublishedFileService/QueryFiles). Looking up a
    | single Workshop item by URL/ID and installing/registering mods works
    | without this.
    |
    | Get a free key at https://steamcommunity.com/dev/apikey, then set it
    | as an environment variable on the panel — NOT in this file:
    |
    |   PALWORLD_MODS_STEAM_API_KEY=your-key-here
    |
    | Add that to the panel's .env file (or however your host manages panel
    | env vars) and it'll be picked up automatically. Never commit a real
    | key into this file or into version control.
    |
    */
    'steam_api_key' => env('PALWORLD_MODS_STEAM_API_KEY'),
];
