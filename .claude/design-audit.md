# Apple Design Audit — Groups

Audit of this plugin against the `apple-design` skill (`.claude/skills/apple-design/SKILL.md`), 2026-10-24. Section references (e.g. §16 *Agency*) point to headings in that skill. The audit of all five Comms.Church plugins is in the shared doc: https://claude.ai/artifact/GGvMskv9nX4bc82HuR4wmS

## Overview

The five plugins have 61 design issues between them: 4 Critical, 13 High, 20 Medium and 24 Low. The worst ones lose people's work or leave them stuck, not visual polish: an editor that hangs on a failed save, sermon edits lost without warning, a confirmation that never fires, and future event dates deleted with no warning.

Each finding gives **What** is wrong, **Why** it matters (citing the section and heading of the vendored `apple-design` skill, e.g. §16 *Agency*), and a **Fix** in the plugin's own stack: plain CSS, jQuery or plain JS, and WordPress PHP. Severity is ranked by harm to the person using it:

- **Critical:** data loss, or a broken layout most visitors will see
- **High:** blocks a task, excludes keyboard or screen-reader users, or shows a wrong state
- **Medium:** friction, unclear feedback, or contrast below WCAG minimums
- **Low:** polish and consistency

The skill is mostly about gesture-driven motion (springs, flicks, momentum). These plugins have no draggable UI, so most citations come from §1 Response, §7 Spatial consistency, §12 Materials, §14 Reduced motion & accessibility, §15 Typography and §16 Design foundations.

### Worst issues across all plugins

| Rank | Plugin | Finding | Severity | Fixed in this pass |
| --- | --- | --- | --- | --- |
| 1 | Recurring Events | Changing a pattern permanently deletes future dates without warning | Critical | In part (warning added) |
| 2 | Sermon Suite | A failed save leaves the editor stuck on "Saving…" | Critical | Yes |
| 3 | Sermon Suite | No warning before leaving with unsaved edits | Critical | Yes |
| 4 | PCO TEC | Events without a featured image show an empty gray box | Critical | Yes |
| 5 | PCO TEC | "Force Full Re-sync" never asks for confirmation | High | Yes |
| 6 | PCO | Add-to-Calendar menu opens upward and can't be used from the keyboard | High | Yes |
| 7 | Sermon Suite, PCO TEC | No visible keyboard focus | High | Yes |
| 8 | PCO | Generator preview doesn't match the live page | High | Yes |
| 9 | Groups | Filtering changes the page silently | High | Yes |
| 10 | Groups, PCO, PCO TEC | Cards lift on hover but aren't links | High / Medium / Low | Yes in Groups and PCO; TEC still open |

A pattern across the suite: Groups and PCO already handle reduced motion, focus rings and container sizing well, while Sermon Suite and PCO TEC don't. A shared base stylesheet for focus, press and reduced-motion styles would stop these drifting apart again.

## Comms Church Groups

Groups has 12 findings and the strongest base of the five. It already has reduced-motion handling, `:focus-visible` rings, and filter bar labels for screen readers. Its biggest gaps are the live filter, which gives no feedback, and hover motion that suggests the whole card is clickable when it isn't. Files: `assets/groups.css`, `assets/groups.js`, `includes/class-ccgrp-renderer.php`, `includes/class-ccgrp-admin.php`.

### 1. Filtering changes the page silently (High)

**What:** Typing in search or changing the type filter hides cards without saying how many are left. Screen readers aren't told at all, because `.ccgrp-noresults` toggles `hidden` with no live region. When nothing matches, the page offers no way back to the full list.

**Why:** §16 *Design foundations*, "expose ongoing status", one of the four feedback kinds. §16 *Wayfinding*: "How do I get out? Never trap the user."

**Fix** (`class-ccgrp-renderer.php` + `groups.js`):

```php
<p class="ccgrp-status" role="status" aria-live="polite"></p>
<p class="ccgrp-noresults" hidden>
  <?php esc_html_e( 'No groups match those filters.', 'comms-church-groups' ); ?>
  <button type="button" class="ccgrp-reset"><?php esc_html_e( 'Show all groups', 'comms-church-groups' ); ?></button>
</p>
```

```js
if ( status ) status.textContent = ( q || t ) ? shown + ' of ' + cards.length + ' groups' : '';
reset.addEventListener( 'click', function () { search.value = ''; if ( type ) type.value = ''; apply(); search.focus(); } );
```

### 2. Card hover suggests the whole card is a link (High)

**What:** `.ccgrp-card:hover` lifts the card 4 px and adds a deep shadow, but only the "Join this group" button inside it does anything. People click the card body and nothing happens.

**Why:** §16 *Familiarity*: "things that look the same must behave the same." Elsewhere, including Sermon Suite's cards, a lifting card is a link.

**Fix:** move the affordance to the button. If the card should be clickable, stretch the button's hit area over it.

```css
.ccgrp-card:hover { transform: none; box-shadow: none; border-color: color-mix(in srgb, currentColor 22%, transparent); }
/* optional whole-card target */
.ccgrp-card { position: relative; }
.ccgrp-btn::after { content: ""; position: absolute; inset: 0; border-radius: var(--ccgrp-radius); }
```

### 3. The search and type fields are barely visible (Medium)

**What:** `.ccgrp-search` and `.ccgrp-type` have a transparent background and a 12% `currentColor` border, about 1.3:1 against white. On most themes, the filter bar reads as empty space.

**Why:** §16 *Simplicity*: use hierarchy "order, spacing, contrast" so the important thing is obvious. §14: `prefers-contrast: more` calls for "a defined, contrasting border."

**Fix:**

```css
.ccgrp-search, .ccgrp-type { border-color: color-mix(in srgb, currentColor 30%, transparent); background: var(--cc-color-surface, #fff); }
@media (prefers-contrast: more) { .ccgrp { --ccgrp-border: currentColor; } }
```

### 4. Button text color is fixed, and the button doesn't react on press (Medium)

**What:** `.ccgrp-btn` hard-codes `color: #fff`. A site that sets a light `--ccgrp-accent`, such as a pale sage, gets white text on a pale button. Hover changes brightness, but there is no `:active` state.

**Why:** §1 *Response*: "Highlight a button the instant it's pressed." §16 *Craft*: "colors that adapt."

**Fix:**

```css
.ccgrp { --ccgrp-on-accent: var(--cc-color-on-primary, #fff); }
.ccgrp-btn, .ccgrp-btn:hover { color: var(--ccgrp-on-accent); }
.ccgrp-btn:active { transform: scale(0.97); transition: transform 100ms ease-out; }
```

### 5. The accent color setting is a plain text box (Medium)

**What:** *Accent color* is `<input type="text">` with only a placeholder hint. A typo like `556136` (no `#`) saves without complaint and silently breaks the variable.

**Why:** §16 *Familiarity*: build on controls people already know, here the color picker. §16: "validate inline (not on submit)."

**Fix:** use WordPress's built-in picker and sanitize on save:

```php
register_setting( 'ccgrp_settings', 'ccgrp_brand_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
// enqueue on this screen:
wp_enqueue_style( 'wp-color-picker' );
wp_enqueue_script( 'wp-color-picker' );
wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){ $("#ccgrp_brand_color").wpColorPicker(); });' );
```

### 6. "Test connection" tests the saved credentials, not the ones typed (Medium)

**What:** The test button sits in its own form below the settings and checks the *saved* App ID and Secret. Someone who pastes new credentials and clicks Test gets a result for the old ones.

**Why:** §16 *Grouping & mapping*: "place a control near what it affects and arrange controls to mirror what they change."

**Fix:** rename the button to "Test saved connection" and show it only after saving. Or run the test automatically after a save that changes credentials (`update_option_ccgrp_secret`) and show the result in the settings-saved notice.

### 7. Search waits 120 ms before filtering (Low)

**What:** `groups.js` debounces input by 120 ms, even though it only filters cards already in memory. A typical page has a few dozen cards, so the delay adds lag without saving any real work.

**Why:** §1 *Response*: "Audit debounces, artificial timers… Anything on the input path that isn't essential is a regression."

**Fix:** `search.addEventListener( 'input', apply );`. To guard very large lists, batch with `requestAnimationFrame` instead of a timer.

### 8. Emoji icons are read aloud (Low)

**What:** `.ccgrp-meta--when::before { content: "🗓" }` and `📍` are generated content, so screen readers announce "spiral calendar" before each schedule. They also look different on every OS.

**Why:** §16 *Craft*: "clear iconography."

**Fix:** at minimum, give the emoji an empty alt text with `content: "🗓" / "";`. Better, use an inline SVG mask: `mask: url("data:image/svg+xml,...") center/contain no-repeat; background: currentColor; width: 1em; height: 1em;`.

### 9. Cards with and without images don't line up (Low)

**What:** `.ccgrp-card__media` only renders when a group has an image, so a row can mix 16:10 headers with headerless cards and the titles sit at different heights.

**Why:** §16 *Craft*: "misaligned icons… read as carelessness."

**Fix:** always render the media block. With no image, fill it with an accent tint and the group-type initial: `background: color-mix(in srgb, var(--ccgrp-accent) 14%, transparent);`.

### 10. Links open new tabs without saying so (Low)

**What:** "Join this group" opens Planning Center in a new tab with no visible or announced hint.

**Why:** §16 *Wayfinding*: "Where can I go?"

**Fix:** add `<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'comms-church-groups' ); ?></span>` and a small ↗ SVG marked `aria-hidden="true"`.

### 11. The Secret is printed back into the settings page (Low)

**What:** The Secret field is `type="password"` but has `value="<?php echo esc_attr( get_option( 'ccgrp_secret' ) ); ?>"`, so the real secret sits in the page source for anyone viewing it.

**Why:** §16 *Responsibility*: "Privacy… only for what's needed."

**Fix:** render an empty field with a "Saved — leave blank to keep" placeholder, and only update the option when the submitted value is non-empty.

### 12. Cards pop in and out while filtering (Low)

**What:** Filtered cards switch `hidden` instantly, so the grid reflows in one jump and it's hard to see what changed.

**Why:** §14: "fade big surfaces out during a large reposition and back in once settled." §7 *Spatial consistency*.

**Fix:** fade matching cards in over 150 ms with `@starting-style { .ccgrp-card { opacity: 0; } }` and `transition: opacity .15s`. Turn it off under `prefers-reduced-motion`, which the file already handles.

## Fixes applied in this pass

I applied 29 of the 61 fixes in full and 5 more in part, covering every Critical and High finding, on the `claude/quirky-feynman-2ppasd` branch of each repo. Each repo also got the `apple-design` skill and a Markdown copy of its section, both under `.claude/`, which the release zip already excludes.

| Plugin | Applied in full | Applied in part | Still open |
| --- | --- | --- | --- |
| Sermon Suite | 1, 2, 3, 4, 5, 6, 7 | 8 (`composes:` bug only), 9 (heading tracking only), 11 (iframe titles, remove-button label) | 10, 12–17 |
| Groups | 1, 2, 3, 4, 7 | — | 5, 6, 8–12 |
| PCO | 1, 2, 3, 4, 7, 8 | — | 5, 6, 9–12 |
| PCO TEC | 1, 2, 3, 4, 5, 6, 7, 8 | — | 9–12 |
| Recurring Events | 4, 5, 8 | 1 (warning only), 2 (summary line only) | 3, 6, 7 |

Two changes need a second opinion before going further:

- **Recurring Events #1:** I added the warning but kept `wp_delete_post( …, true )`. Switching to `wp_trash_post()` changes what `children_of()` sees, so it needs its own change and test.
- **PCO #1:** making the menu drop down meant removing `overflow: hidden` from `.ccpco-card`. The image now rounds its own corners. Check a theme that adds a border to cards.

**Verification.** The repos' Playwright suites couldn't run here: the environment's network policy blocks `wordpress.org` and `playground.wordpress.net`, which WordPress Playground needs to boot. Instead, every edited PHP file passes `php -l` and every edited script passes `node --check`. A headless-Chromium harness loaded each plugin's real CSS and JS against markup copied from its renderer and passed 39 of 39 checks. Those covered the menu's direction, focus and keyboard behavior and that the card no longer clips it; the Groups count, reset and focus return; the TEC confirm and Syncing state; the recurrence summary text; and the Sermon Suite chip, badge and focus fixes. Run `npm test` in each repo locally before merging.
