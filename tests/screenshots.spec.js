// Regenerates marketing/docs screenshots from the live (Playground) app.
// Run with: npm run screenshots  →  writes to screenshots/*.png
// Note: no PCO credentials in Playground, so only admin UI screens are
// captured — front-end groups render an empty state without the API.
const { test } = require('@playwright/test');

test.use({ viewport: { width: 1440, height: 900 } });

const shots = [
  ['/wp-admin/options-general.php?page=ccgrp-settings', 'settings.png'],
  
];

for (const [path, file] of shots) {
  test(`capture ${file}`, async ({ page }) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `screenshots/${file}`, fullPage: true });
  });
}
