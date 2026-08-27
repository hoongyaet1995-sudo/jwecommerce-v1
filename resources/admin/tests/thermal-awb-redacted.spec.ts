import { expect, test } from '@playwright/test';

const printControls = ['Print Thermal AWB', 'Print Waybill'] as const;

for (const controlName of printControls) {
  test(`redacted shipment blocks ${controlName} without invoking print side effects`, async ({ page }) => {
    await page.goto('/tests/fixtures/thermal-awb-redacted.html');

    const control = page.getByRole('button', { name: controlName });
    await expect(control).toBeVisible();
    await control.evaluate((button: HTMLButtonElement) => button.click());
    await page.waitForTimeout(500);

    expect(await page.evaluate(() => window.__THERMAL_AWB_EFFECTS__)).toEqual({
      onPrint: 0,
      windowPrint: 0,
    });
    await expect(control).toBeDisabled();
    await expect(page.getByText(
      'Printing unavailable: authorized recipient and label details are not included in shipment-list responses.',
      { exact: true },
    )).toBeVisible();
  });
}
