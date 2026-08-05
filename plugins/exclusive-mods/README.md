# Exclusive Mods

Steam Workshop mod management for game servers, plus a "Players Online" widget
on the Console page. Built as a generic driver framework — only Palworld/UE4SS
is implemented today; other games' eggs will show a "not supported yet" state
until a driver is registered for them.

## Installation

1. Copy this folder to `plugins/exclusive-mods` in your Pelican Panel installation.
2. In the admin panel, go to **Plugins**, find "Exclusive Mods", and install it.
   This runs the plugin's own migrations (it does not modify any core tables).
3. Enable it.
4. (Optional) Open the plugin's **Settings** action in the admin Plugins list to
   set a Steam Web API key — only needed to enable Workshop *search*; installing
   a mod by pasting a direct Workshop URL or item ID works without a key.

## Mapping an egg to the Palworld driver

This first release does not ship an admin UI for mapping an Egg to a mod driver
— insert the mapping directly, e.g. via `php artisan tinker`:

```php
\Exclusive\Mods\Models\EggDriver::create([
    'egg_id' => \App\Models\Egg::where('name', 'Palworld')->firstOrFail()->id,
    'driver' => 'palworld-ue4ss',
]);
```

Once mapped, the server's **Mods** tab becomes fully functional for servers
running that egg. Servers on any other egg will see a "not supported for this
game yet" message instead of the mod list.

## Players Online

The Players Online widget (shown above the console on the Console tab) reads a
Palworld server's built-in REST API. It looks for `REST_API_PORT` and
`ADMIN_PASSWORD` startup variables on the server's egg first; if those aren't
defined, configure them per-server directly in the
`exclusive_mods_server_query_configs` table until an admin UI is added.

## Known limitations / flagged assumptions

These were called out during planning and should be verified against current
Palworld/Steam documentation before relying on this in production:

- The exact Palworld REST API endpoint path, port, auth scheme and response
  shape (`PalworldRestQueryService`).
- Whether Palworld Workshop items are directly HTTP-fetchable for installation,
  or require a SteamCMD download step (`PalworldUe4ssDriver::install()`).
- The exact UE4SS mod install path inside the server volume.
- Current Steam Web API endpoint versions (`SteamWorkshopService`).
- No admin UI yet for the Egg → driver mapping (see above) — table only.
- Mod-manager actions are gated on the closest existing `SubuserPermission`
  cases (`file.read`/`file.update`/`file.delete`) since this plugin can't add
  new cases to that core enum.
