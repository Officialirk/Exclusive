# Exclusive Theme

A dark navy/amber color palette and custom font set for Pelican Panel — built
entirely through Filament's built-in panel customization API (`colors()` +
`font()`/`monoFont()`), the same pattern Pelican's own reference theme plugin
uses ([pterodactyl-theme](https://github.com/pelican-dev/plugins/tree/main/pterodactyl-theme)).

## Installation

1. Copy this folder to `plugins/exclusive-theme` in your Pelican Panel installation.
2. In the admin panel, go to **Plugins**, find "Exclusive Theme", and install it.
3. Enable it. Only one theme plugin can be active at a time.

No build step is required — this plugin ships no custom CSS/JS and no Vite
assets, so there's nothing for `yarn build` to need to pick up for it
specifically.

## Notes / known limitations

An earlier version of this plugin shipped a custom `theme.css`/`theme.js`
bundle wired through a Filament render hook and `@vite()`, to get gauge-style
CPU/Memory/Disk bars and a retinted console terminal beyond what `colors()`/
`font()` can do alone. That approach depends on a successful `yarn build`
having produced a Vite manifest entry for the plugin's assets — on servers
where that build doesn't run (or fails, e.g. a missing `yarn`/`node`
toolchain, or a background queue worker not running to process the install
job), every page throws "Unable to locate file in Vite manifest" instead of
silently degrading. This version trades away those extra visual touches
(gauge bars, console retint, bespoke sidebar styling) for something that
can't break that way — colors and fonts only, applied via the same
mechanism Pelican's own theme plugin uses.
