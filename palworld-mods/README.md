# Palworld Mods (by Officialirk)

A [Pelican Panel](https://pelican.dev) plugin, built the same way as the plugins in
[pelican-dev/plugins](https://github.com/pelican-dev/plugins) (e.g. `rust-umod`, `minecraft-modrinth`).

It lets users browse, install, update and uninstall Palworld mods from
[Thunderstore](https://thunderstore.io/c/palworld/) **and** the official
[Steam Workshop](https://steamcommunity.com/app/1623730/workshop/) directly from
the server's panel view — no manual SFTP/upload/unzip required.

## Why Thunderstore?

Palworld doesn't have a single official mod hub. The two realistic options for an
API a plugin can query without asking every user for a personal key are:

- **Nexus Mods** — the biggest source of Palworld mods, but its API requires a
  per-user (often premium) API key and isn't meant for anonymous/server-side use.
- **Thunderstore** — hosts a large and growing [Palworld community](https://thunderstore.io/c/palworld/)
  and exposes a free, public, unauthenticated JSON API (the same one Modrinth-style
  mod managers like r2modman use). This is what the plugin uses.

If you'd rather integrate Nexus Mods, the `PalworldModsService` class is the only
place that talks to an external API — swap the HTTP calls there and everything
else (install/update/uninstall/UI) keeps working.

## Features

### Thunderstore

- Browse & search the Palworld mod list, sorted by popularity
- One-click install into a folder you choose:
  - `Pal/Content/Paks/LogicMods` — most Blueprint/pak mods
  - `Pal/Content/Paks/~mods` — legacy pak mods
  - `Pal/Binaries/Win64/ue4ss/Mods` — UE4SS Lua script mods

### Steam Workshop

- A **Steam Workshop** browse/search tab, if you've set a Steam Web API key
  (see Setup below) — without a key, that tab is empty but everything else
  still works
- **Add from Steam Workshop** button — paste a Workshop URL or item ID directly,
  no key needed for this
- For items Steam exposes a direct download for: installs into
  `Mods/Workshop/<id>/` and reads its `Info.json` to enable it in
  `Mods/PalModSettings.ini` (`bGlobalEnableMod=true` +
  `ActiveModList=<PackageName>`) — the same mechanism Palworld's official
  1.0+ mod loader uses
- **In practice, most real Workshop items don't have a direct download**
  (see the caveat below) — for those, a **Register uploaded mod** button lets
  you finish the last step yourself: download the item via SteamCMD/Steam
  elsewhere, upload the folder into `Mods/Workshop/` via the panel's file
  manager, then Register just reads its `Info.json` and wires it into
  `PalModSettings.ini` for you
- Update checks against Steam's own "last updated" timestamp for the item

### Both

- Tracks everything it installed (in a `.palworld-mods-metadata.json` file on
  the server) so it can offer **Update** and **Uninstall**, from either source,
  in one unified "Installed" tab
- Quick links to open the LogicMods / UE4SS Mods / Workshop folders in the
  file manager

## Setup

1. Copy this `palworld-mods` folder into your panel's `plugins/` directory.
2. Run `php artisan p:plugin:install palworld-mods` from the panel root (or use
   **Admin → Plugins** in the panel UI).
3. Edit your Palworld egg (**Admin → Nests → your Palworld egg**) and add either:
   - the tag `palworld` to the egg's **Tags**, or
   - the feature `palworld_mods` to the egg's **Features**

   (Not both mixed up — it checks `palworld` specifically in **Tags**, and
   `palworld_mods` specifically in **Features**. One or the other, in the
   right box.)

   Servers using that egg will then get a **Mods** entry in their sidebar.
4. *(Optional)* To enable the Steam Workshop **browse/search** tab, get a free
   key at [steamcommunity.com/dev/apikey](https://steamcommunity.com/dev/apikey),
   then go to **Admin → Plugins → Palworld Mods → Settings** and paste it into
   the **Steam Web API key** field. The panel writes it to its own `.env` for
   you — no server/shell access needed, and it's never stored in this plugin's
   code or committed anywhere.

   (If you do have shell access to the panel and would rather set it directly,
   you can instead add `PALWORLD_MODS_STEAM_API_KEY=your-key-here` to the
   panel's `.env` file and run `php artisan config:clear` — same result,
   either way works.)

   This step is entirely optional: adding a specific mod by URL/ID, and
   installing/registering it, both work without a key.

## Notes & limitations

- Palworld mods aren't as uniform as, say, Minecraft `.jar` files — some are pak
  files, some are UE4SS Lua scripts, and mod authors don't always agree on where
  their zip's contents should end up. The plugin extracts a mod's Thunderstore
  archive directly into the folder you pick and strips out Thunderstore's own
  packaging files (`manifest.json`, `README.md`, `CHANGELOG.md`, `icon.png`).
  **Always check a mod's Thunderstore page** if you're unsure which folder it
  needs — install to the wrong one and the mod just won't load.
- UE4SS itself is not installed by this plugin — your egg/image needs to already
  have UE4SS set up for `ue4ss/Mods` Lua mods to do anything.
- The Thunderstore package list is cached for 15 minutes per panel instance to
  keep things fast and avoid hammering their API.
- **Steam Workshop caveat (the important one):** the plugin fetches Workshop
  item metadata through Steam's public `GetPublishedFileDetails` API, and
  *can* download a mod directly when Steam's response includes a `file_url` —
  but in practice, **most real Palworld Workshop items don't have one**; they
  live in Steam's authenticated depot-download system instead
  (`steamcmd +workshop_download_item`), which no plain HTTP request — keyed or
  not — can reach. When that's the case the plugin tells you clearly instead
  of failing silently; use the **Register uploaded mod** action after placing
  the files yourself. Giving the plugin shell/SteamCMD access to actually run
  that download itself isn't something this plugin will ever do — that's a
  much bigger trust boundary than a panel plugin should ask for.
- Workshop mods that ship an `Info.json` with `InstallRule.IsServer: false`
  (client-only mods, e.g. pure UI/keybind mods) will still get placed in
  `Mods/Workshop/`, but Palworld's own mod loader — not this plugin — decides
  at runtime whether to actually deploy them for a dedicated server.

## License

GPL-3.0, matching the rest of the Pelican plugin ecosystem.
