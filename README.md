# Comms.Church — Planning Center Groups

A public small-groups finder powered by Planning Center Groups. Browse, search, and
filter groups, then hand off to Church Center for the actual signup. API credentials
are stored server-side and never exposed to visitors.

## Status

**0.2.0 — pre-release.** Feature-complete for a first client: API client, caching,
rendering, shortcodes, Bricks elements, Gutenberg blocks, and GitHub auto-updates are
built and tested. The PCO field mapping still needs one verification pass against a live
account (see [Verifying the field mapping](#verifying-the-field-mapping)) — that's the
only thing standing between this and a real deployment.

## Installation (first time on a new site)

This plugin is not on WordPress.org, so the first install is manual:

1. Download the latest `comms-church-groups.zip` from Releases.
2. In WordPress: **Plugins → Add New → Upload Plugin**.
3. Upload the zip, install, activate.
4. Go to **Settings → PCO Groups** and enter your Planning Center credentials.

Credentials come from Planning Center → **Developer → Personal Access Tokens**. The
token needs access to **Groups**.

## Usage

### Bricks elements

With Bricks active, two native elements appear under the **Planning Center** category:

| Element | Use |
|---|---|
| **PCO Groups Finder** | The full finder — type filter, columns, search, and per-field toggles |
| **PCO Group Card** | One specific group, by ID |

Both inherit the site's design automatically and expose an optional accent-color
override on the Style tab. They delegate to the same code path as the shortcodes, so
there's one implementation to keep correct.

### Gutenberg blocks

Two server-side-rendered blocks under the **Comms.Church** category: **PCO Groups
Finder** and **PCO Group Card**. The card block offers a *group picker* populated from
the live account, so an editor never has to hunt for a numeric ID. Previews in the
editor are the real front-end output, not a separate implementation.

### Shortcodes

Put these anywhere shortcodes run — a page, a Bricks Shortcode element, a block.

| Shortcode | What it does |
|---|---|
| `[cc_groups]` | All groups, with search + type filter |
| `[cc_groups columns="2" filter="no"]` | Two columns, no filter bar |
| `[cc_groups type="Men's"]` | Only one group type |
| `[cc_groups limit="3" filter="no"]` | A teaser row for a home page |
| `[cc_group id="123"]` | One specific group |

Other attributes: `image`, `schedule`, `location` (all `yes`/`no`), and `empty_text`.

## How it behaves when things go wrong

- **Not configured** — visitors see nothing at all; admins see a link to the settings page.
- **PCO is down or rate-limits** — the cache serves the last good response for up to 24h
  rather than showing an error. A `429` trips a 60-second circuit breaker so we stop
  hammering the API.
- **PCO returns an error** — visitors see nothing; admins see the reason.
- **A group is missing fields** — the card degrades (drops that row) instead of fataling.

## Styling

Markup uses single, low-specificity `.ccgrp-*` classes so a theme or Bricks stylesheet
can restyle anything without `!important`. The card picks up these CSS variables if the
site defines them, so on a comms.church build it inherits the site's design automatically:

`--cc-color-primary`, `--cc-color-surface`, `--cc-radius-card`, `--cc-radius-button`,
`--cc-shadow-card-hover`

Or set an accent directly in **Settings → PCO Groups → Accent color**.

## Verifying the field mapping

Planning Center versions its API by date, and Group attribute names have moved between
versions. Rather than trusting documentation, the plugin ships an inspector:

**Settings → PCO Groups → API Inspector → Inspect a group**

It fetches one real group from the connected account and shows two things side by side:
what the plugin mapped (empty values flagged in red) and the raw attributes PCO actually
returned. If a field comes back empty, the fix is a one-line change in
`CCGRP_API::flatten()` — the renderer never touches raw API shapes.

## Architecture

```
comms-church-groups.php     bootstrap, PHP guard, asset registration
includes/
  class-ccgrp-api.php       PCO Groups v2 client; pagination; flatten() JSON:API -> flat arrays
  class-ccgrp-cache.php     transients + generation invalidation + stale fallback + circuit breaker
  class-ccgrp-renderer.php  all HTML; escaping; graceful degradation
  class-ccgrp-shortcodes.php  [cc_groups], [cc_group]
  class-ccgrp-admin.php     settings, connection test, API Inspector
  class-ccgrp-bricks.php    registers Bricks elements when Bricks is active
  class-ccgrp-blocks.php    two server-side-rendered Gutenberg blocks
  class-ccgrp-updater.php   GitHub Releases -> native WP update UI
  bricks/element-*.php      Groups Finder + Group Card elements
assets/groups.css|js        front-end styles + progressive-enhancement filtering
assets/blocks.js            block editor UI (no build step)
```

The renderer never sees a raw PCO payload — `flatten()` is the only place that knows
JSON:API, which is what keeps an API change to a one-file fix.

## Updates

After the first manual install, the plugin checks GitHub Releases and updates appear in
the normal **Plugins** and **Dashboard → Updates** screens. A "Check for updates" link on
the Plugins row forces an immediate check. No third-party service is involved.

## Roadmap

- Optional map view for groups with coordinates
- Group detail pages (currently the CTA hands off to Church Center)

## License

GPL-2.0+
