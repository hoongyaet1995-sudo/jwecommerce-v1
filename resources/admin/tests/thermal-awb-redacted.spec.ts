import { expect, test } from '@playwright/test';

const printControls = ['Print Thermal AWB', 'Download AWB PDF'] as const;

for (const controlName of printControls) {
  test(`redacted shipment blocks ${controlName} without invoking print side effects`, async ({ page }) => {
    await page.goto('/tests/fixtures/thermal-awb-redacted.html');

    const control = page.getByRole('button', { name: controlName });
    await expect(control).toBeVisible();
    await control.evaluate((button: HTMLButtonElement) => button.click());
    await page.waitForTimeout(500);

    expect(await page.evaluate(() => window.__THERMAL_AWB_EFFECTS__)).toEqual({ windowOpen: 0 });
    await expect(control).toBeDisabled();
    await expect(page.getByText(
      'Official EasyParcel AWB is still pending. Refresh the provider status or reconcile the shipment before printing.',
      { exact: true },
    ).first()).toBeVisible();
    await expect(page.getByText('Aurora Commerce Hub HQ')).toHaveCount(0);
    await expect(page.getByText('SCAN-AWB')).toHaveCount(0);
  });
}
