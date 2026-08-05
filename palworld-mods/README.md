# Palworld Mods (by Officialirk)

A [Pelican Panel](https://pelican.dev) plugin, built the same way as the plugins in
[pelican-dev/plugins](https://github.com/pelican-dev/plugins) (e.g. `rust-umod`, `minecraft-modrinth`).

It lets users browse, install, update and uninstall Palworld mods from
[Thunderstore](https://thunderstore.io/c/palworld/) directly from the server's file
management page in the panel — no manual SFTP/upload/unzip required.

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

- Browse & search the Palworld mod list, sorted by popularity
- One-click install into a folder you choose:
  - `Pal/Content/Paks/LogicMods` — most Blueprint/pak mods
  - `Pal/Content/Paks/~mods` — legacy pak mods
  - `Pal/Binaries/Win64/ue4ss/Mods` — UE4SS Lua script mods
- Tracks what it installed (in a `.palworld-mods-metadata.json` file on the server)
  so it can offer **Update** and **Uninstall** for anything it installed
- "Installed" tab listing everything the plugin manages on that server
- Quick links to open the LogicMods / UE4SS Mods folders in the file manager

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

## License

GPL-3.0, matching the rest of the Pelican plugin ecosystem.
