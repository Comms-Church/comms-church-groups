// Release smoke suite — green, or no release.
// Runs against WordPress Playground with the plugin active and a /groups/ page
// seeded with the [cc_groups] shortcode (tests/blueprint.json).
// No Planning Center credentials are configured, so the plugin must degrade
// gracefully — silence for visitors, a friendly notice for admins, never a fatal.
const { test, expect } = require('@playwright/test');

// PHP problems surface as text in the page body, not HTTP errors.
const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

async function expectClean(page) {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(PHP_ERRORS);
}

test.describe('Public pages', () => {
  test('front page renders without PHP errors', async ({ page }) => {
    await page.goto('/');
    await expectClean(page);
  });

  test('shortcode page renders without PHP errors and registers shortcodes', async ({ page }) => {
    await page.goto('/?pagename=groups');
    await expectClean(page);
    const body = await page.locator('body').innerText();
    // Raw shortcode text in output means the shortcode failed to register.
    expect(body).not.toContain('[cc_group');
  });
});

test.describe('Admin pages', () => {
  test('settings page renders without PHP errors', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=ccgrp-settings');
    // Playground --login auto-authenticates as admin; if we ended up on a
    // login form, auth is broken and every admin assertion is meaningless.
    await expect(page.locator('#wpwrap')).toBeVisible();
    await expectClean(page);
    const body = await page.locator('body').innerText();
    expect(body).toContain('Planning Center Groups');
  });

  test('settings page offers the connection test and API inspector', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=ccgrp-settings');
    await expect(page.locator('button[name="ccgrp_test"]')).toBeVisible();
    await expect(page.locator('button[name="ccgrp_inspect"]')).toBeVisible();
  });

  test('connection test fails gracefully with no credentials', async ({ page }) => {
    // The failure path matters more than the success path: a church with a typo
    // in their token must get a readable message, not a stack trace.
    await page.goto('/wp-admin/options-general.php?page=ccgrp-settings');
    await page.locator('button[name="ccgrp_test"]').click();
    await expect(page.locator('#wpwrap')).toBeVisible();
    await expectClean(page);
  });

  test('plugins screen renders with the plugin active', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    await expect(page.locator('#wpwrap')).toBeVisible();
    await expectClean(page);
    const body = await page.locator('body').innerText();
    expect(body).toContain('Planning Center Groups');
  });
});
