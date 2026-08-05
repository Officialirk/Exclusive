# Exclusive Theme

A dark void/gold "luxury" reskin of Pelican Panel — custom fonts (IBM Plex Mono,
Inter, Playfair Display), a gold/void color palette, gauge-style CPU/Memory/Disk
bars on the server Console page, and a retinted console/terminal.

## Installation

1. Copy this folder to `plugins/exclusive-theme` in your Pelican Panel installation.
2. In the admin panel, go to **Plugins**, find "Exclusive Theme", and install it.
3. Enable it. Only one theme plugin can be active at a time.

## Notes / known limitations

This plugin only retints the panel's existing markup via CSS and a small amount
of client-side JS — it does not fork any core Blade views. As a result:

- The sidebar's server-switcher list keeps its default Filament structure and
  layout; only its colors/borders are retinted, not its bespoke card layout
  from the original mockup.
- The CPU/Memory/Disk gauge bars and the console's color palette are applied by
  JavaScript after each render (see `resources/js/theme.js`), since the
  underlying widgets are core-owned. This is a deliberate trade-off to avoid
  editing core files; the visual match will be close but not pixel-perfect in
  every case.
