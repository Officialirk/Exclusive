# Exclusive

Two [Pelican Panel](https://github.com/pelican-dev/panel) plugins that turn the
default panel into "Exclusive — Server Control": a dark void/gold themed reskin,
plus a Steam Workshop mod manager and Players Online widget for supported games.

## Plugins

- **`plugins/exclusive-theme`** — visual restyle (colors, fonts, gauges, console
  retinting). Category: `theme`.
- **`plugins/exclusive-mods`** — Steam Workshop mod management (Palworld/UE4SS
  driver included, generic framework for adding more games) and a Players
  Online console widget. Category: `plugin`.

See each plugin's own `README.md` for installation steps and known limitations.

## Installation

Both plugins are self-contained Pelican Panel plugin packages. In an existing
Pelican Panel installation:

```sh
cp -r plugins/exclusive-theme  /path/to/panel/plugins/
cp -r plugins/exclusive-mods   /path/to/panel/plugins/
```

Then, in the panel's admin area, go to **Plugins** and install + enable each
one from there (this runs their bundled migrations and rebuilds panel assets).

## Status

This is a first pass built directly against the Pelican Panel source (Laravel
13 + FilamentPHP v5.6) rather than against a running instance — local runtime
verification (installing/enabling through a live panel, confirming rendered
CSS/JS, exercising a real Palworld server) was not completed for this
environment; see the "Known limitations / flagged assumptions" sections in each
plugin's README for what still needs verifying against a real deployment before
production use.
