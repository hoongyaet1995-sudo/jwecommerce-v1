// Run in an isolated playwright-cli session with a populated local cart:
// npx --yes --package @playwright/cli playwright-cli -s=checkout-width run-code --filename=tests/Browser/checkout-mobile-width.js
async (page) => {
    const failures = [];
    const results = [];
    for (const width of [390, 320, 375, 768, 1280]) {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('http://127.0.0.1:8500/checkout');
        await page.locator('input[name="postal_code"]').fill('50000');
        await page.waitForFunction(() => document.querySelector('input[name="delivery"]'));
        await page.locator('input[name="delivery"]').first().check();
        await page.locator('input[name="delivery"]').first().dispatchEvent('change');
        const result = await page.evaluate(() => {
            const apply = document.querySelector('button[form="checkout-coupon-apply-form"]');
            const total = [...document.querySelectorAll('dt')].find(el => el.textContent.trim() === 'Grand Total')?.nextElementSibling?.textContent.trim();
            const submit = [...document.querySelectorAll('aside button')].find(el => !el.hasAttribute('form'));
            return {
                width: innerWidth,
                scroll: document.documentElement.scrollWidth,
                apply: apply?.textContent.trim(),
                submit: submit?.textContent.trim(),
                total,
                icon: Boolean(submit?.querySelector('svg, [data-lucide]')),
            };
        });
        results.push(result);
        if (result.scroll > width) failures.push(`${width}px: page overflows to ${result.scroll}px`);
        if (result.apply !== 'Apply') failures.push(`${width}px: coupon action overwritten`);
        if (!result.submit?.includes(result.total)) failures.push(`${width}px: checkout total stale`);
        if (!result.icon) failures.push(`${width}px: checkout lock icon lost`);
    }
    if (failures.length) throw new Error(failures.join('\n'));
    return results;
}
