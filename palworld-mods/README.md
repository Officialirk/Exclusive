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

- **Add from Steam Workshop** button — paste a Workshop URL or item ID
- Downloads the item, drops it into `Mods/Workshop/<id>/` and reads its
  `Info.json` to enable it in `Mods/PalModSettings.ini`
  (`bGlobalEnableMod=true` + `ActiveModList=<PackageName>`) — the same
  mechanism Palworld's official 1.0+ mod loader uses, so no manual folder
  guessing is needed for these
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

   Servers using that egg will then get a **Mods** entry in their sidebar.

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
- **Steam Workshop caveat:** the plugin fetches Workshop items through Steam's
  public `GetPublishedFileDetails` API and downloads them via the same direct
  `file_url` most Palworld Workshop packages expose (they're just zip files,
  not full Steam depots). If a specific item genuinely has no direct download —
  Steam returns an empty `file_url` — the plugin can't fetch it and will tell
  you so; that content would need SteamCMD's authenticated
  `workshop_download_item` flow instead, which isn't something a panel plugin
  can trigger (it would mean giving the plugin shell/SteamCMD access on the
  server, a much larger trust boundary than a panel plugin should ask for).
- Workshop mods that ship an `Info.json` with `InstallRule.IsServer: false`
  (client-only mods, e.g. pure UI/keybind mods) will still get placed in
  `Mods/Workshop/`, but Palworld's own mod loader — not this plugin — decides
  at runtime whether to actually deploy them for a dedicated server.

## License

GPL-3.0, matching the rest of the Pelican plugin ecosystem.
