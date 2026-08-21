import { expect, test } from '@playwright/test';

const laravelBootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
};

const desktopRoutes = [
  ['/admin', 'Universal Ecommerce Intelligence'],
  ['/admin/commerce', 'Orders & Customer Commerce'],
  ['/admin/orders', 'Order Fulfillment & Transactions'],
  ['/admin/customers', 'Customer Directory & Accounts'],
  ['/admin/catalog', 'Catalog & Product Management'],
  ['/admin/catalog/products', 'Product Catalog & SKU Matrix Admin'],
  ['/admin/catalog/categories', 'Category Management (CRUD)'],
  ['/admin/catalog/collections', 'Collection Management (CRUD)'],
  ['/admin/promotions', 'Promotions & Marketing Rules'],
  ['/admin/shipping', 'Malaysia Shipping & Multi-Carrier Logistics Center'],
] as const;

test.beforeEach(async ({ page }) => {
  await page.addInitScript((bootstrap) => {
    window.__JW_ADMIN__ = bootstrap;
  }, laravelBootstrap);
});

test('core operations direct URLs retain source headings at desktop and mobile', async ({ page }) => {
  for (const [viewportName, viewport] of [
    ['desktop', { width: 1920, height: 900 }],
    ['mobile', { width: 390, height: 844 }],
  ] as const) {
    await page.setViewportSize(viewport);
    for (const [route, heading] of desktopRoutes) {
      await page.goto(route);
      await expect(page.getByText(heading, { exact: true }).first()).toBeVisible();
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      await page.screenshot({ path: `../../output/admin-react-rebuild/task-7/${viewportName}${route.replaceAll('/', '-') || '-dashboard'}.png` });
    }
  }
});

test('commerce hub, order filters, selection, detail, status update, and customer editor preserve source behaviors', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/commerce');
  await page.getByText('Manage Store Orders', { exact: true }).click();
  await expect(page).toHaveURL('/admin/orders');
  await expect(page.locator('tbody tr')).toHaveCount(4);
  await expect(page.locator('tbody tr button')).toHaveCount(4);

  await page.getByPlaceholder('Search order #, customer name, email...').fill('ORD-9842');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.getByRole('button', { name: 'Details' }).click();
  await expect(page.getByText('Update Order Status & Dispatch Customer Notification')).toBeVisible();
  await page.locator('div.fixed select').first().selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Update & Notify' }).click();
  await expect(page.getByText('Order Status Updated', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-order-detail-status-toast.png' });
  await page.getByRole('button', { name: 'Close Order' }).click();

  await page.goto('/admin/commerce');
  await page.getByText('Manage Customers', { exact: true }).click();
  await expect(page).toHaveURL('/admin/customers');
  await expect(page.locator('tbody tr')).toHaveCount(4);
  await expect(page.locator('tbody tr button')).toHaveCount(8);
  await page.getByPlaceholder('Search name or email address...').fill('Elena');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await page.getByRole('button', { name: 'Add Customer' }).click();
  await expect(page.getByText('Create Customer')).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).click();
});

test('catalog tables, product duplication, and every catalog editor state remain source faithful', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/catalog');
  await page.getByText('Manage Products', { exact: true }).click();
  await expect(page).toHaveURL('/admin/catalog/products');
  await expect(page.locator('tbody tr')).toHaveCount(3);
  await expect(page.locator('tbody tr button')).toHaveCount(9);
  await page.getByTitle('Duplicate Product').first().click();
  await expect(page.locator('tbody tr')).toHaveCount(4);
  await page.waitForTimeout(100);
  const duplicateToastCards = page.locator('div.fixed.bottom-5.right-5 > div');
  expect(await duplicateToastCards.count()).toBe(2);
  await expect(page.getByText('Product Published', { exact: true })).toBeVisible();
  expect(await duplicateToastCards.evaluateAll((cards) => cards.map((card) => ({
    text: card.textContent?.trim() ?? '',
    isDefaultBlue: card.className.includes('bg-blue-950/80'),
  })))).toContainEqual({ text: '', isDefaultBlue: true });
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-product-duplicate-malformed-toast.png' });

  await page.getByTitle('Edit Product & Matrix').first().click();
  const productEditor = page.locator('#product-editor-modal');
  await expect(productEditor).toBeVisible();
  await productEditor.getByPlaceholder('e.g. Aurora Studio Pro Wireless Headphones').fill('Task 7 Saved Product');
  await productEditor.getByRole('button', { name: 'Save Product & SKUs' }).click();
  await expect(productEditor).toBeHidden();
  await expect(page.getByText('Task 7 Saved Product', { exact: true })).toBeVisible();

  await page.getByRole('button', { name: 'Add Product' }).click();
  await expect(page.getByText('New Product & Variant Matrix')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-product-create-modal.png' });
  await page.getByRole('button', { name: /Variant Matrix Generator/ }).click();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-product-variants-modal.png' });
  await page.getByRole('button', { name: /Media Gallery/ }).click();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-product-media-modal.png' });
  await page.getByRole('button', { name: /SEO & Social Meta/ }).click();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-product-seo-modal.png' });
  await page.getByRole('button', { name: 'Discard Changes' }).click();

  await page.goto('/admin/catalog/categories');
  await expect(page.locator('tbody tr')).toHaveCount(5);
  await expect(page.locator('tbody tr button')).toHaveCount(10);
  await page.getByRole('button', { name: 'Create Category' }).click();
  await expect(page.getByText('Create New Category')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-category-create-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.getByText('Create New Category')).toBeHidden();
  await page.getByRole('button', { name: 'Create Category' }).click();
  await page.getByPlaceholder('e.g. Smart Wearables & Rings').fill('Task 7 Saved Category');
  await page.getByRole('button', { name: 'Save Category' }).click();
  await expect(page.getByText('Create New Category')).toBeHidden();
  await expect(page.getByText('Task 7 Saved Category', { exact: true })).toBeVisible();
  await expect(page.locator('tbody tr')).toHaveCount(6);
  await page.locator('tbody tr').first().getByRole('button').first().click();
  await expect(page.getByText(/^Edit Category:/)).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-category-edit-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).click();

  await page.goto('/admin/catalog/collections');
  await expect(page.getByText(/^\/collections\//)).toHaveCount(3);
  await page.getByRole('button', { name: 'Create Collection' }).click();
  await expect(page.getByText('Create New Collection')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-collection-create-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.getByText('Create New Collection')).toBeHidden();
  await page.getByRole('button', { name: 'Create Collection' }).click();
  await page.getByPlaceholder('e.g. Summer Tech Essentials 2026').fill('Task 7 Saved Collection');
  await page.getByRole('button', { name: 'Save Collection' }).click();
  await expect(page.getByText('Create New Collection')).toBeHidden();
  await expect(page.getByText('Task 7 Saved Collection', { exact: true })).toBeVisible();
  await expect(page.getByText(/^\/collections\//)).toHaveCount(4);
  await page.locator('img[alt="Summer Tech Essentials 2026"]').locator('xpath=../../..').getByRole('button').first().click();
  await expect(page.getByText(/^Edit Collection:/)).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-catalog-collection-edit-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).click();
});

test('promotions filters, tabs, simulator, usage ledger, and create modal preserve source states', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/promotions');
  await expect(page.getByRole('button', { name: 'Active Campaigns' })).toBeVisible();
  await expect(page.locator('main').getByText('Rule Engine v2.4')).toBeVisible();
  await page.getByPlaceholder('Search campaigns, voucher codes (e.g. RAYA2026), rules...').fill('RAYA');
  await expect(page.locator('main').getByText('No promotion campaigns found')).toBeHidden();
  await page.getByRole('button', { name: 'New Promotion' }).click();
  await expect(page.getByRole('heading', { name: 'Create Promotion Rule' })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-promotions-create-modal-mix-match.png' });
  await page.getByRole('button', { name: /Voucher Code/ }).click();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-promotions-create-modal-voucher.png' });
  await page.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.getByRole('heading', { name: 'Create Promotion Rule' })).toBeHidden();
  await page.getByRole('button', { name: 'New Promotion' }).click();
  await page.getByRole('button', { name: /Mix & Match/ }).click();
  await page.getByPlaceholder('e.g. Streetwear Graphic Tees: Any 3 for RM 99').fill('RAYA Task 7 Saved Promotion');
  await page.getByRole('button', { name: 'Deploy Campaign Rule' }).click();
  await expect(page.getByRole('heading', { name: 'Create Promotion Rule' })).toBeHidden();
  await expect(page.getByText('RAYA Task 7 Saved Promotion', { exact: true })).toBeVisible();

  const promotionToggles = page.locator('button.relative.inline-flex.h-5.w-9');
  const initialToggleClass = await promotionToggles.first().getAttribute('class');
  await promotionToggles.first().click();
  await expect.poll(() => promotionToggles.first().getAttribute('class')).not.toBe(initialToggleClass);
  await page.getByRole('button', { name: 'Copy' }).first().click();
  await expect(page.getByRole('button', { name: 'Copied' }).first()).toBeVisible();
  const deleteButtons = page.getByTitle('Delete promotion');
  const promotionCount = await deleteButtons.count();
  await deleteButtons.first().click();
  await expect(deleteButtons).toHaveCount(promotionCount - 1);

  await page.getByRole('button', { name: 'Test Simulator' }).click();
  await expect(page.getByText('Real-time Checkout Engine & Stacking Simulator')).toBeVisible();
  await page.getByPlaceholder('e.g. RAYA2026').fill('RAYA2026');
  await page.getByRole('button', { name: 'Apply' }).click();
  await page.getByRole('button', { name: 'GrabPay' }).click();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-promotions-simulator-voucher.png' });
  await page.getByText('Usage & Audit Ledger', { exact: true }).click();
  await expect(page.getByPlaceholder('Search by Order #, Customer, or Voucher Code...')).toBeVisible();
  await expect(page.locator('tbody tr')).toHaveCount(4);
  await page.getByPlaceholder('Search by Order #, Customer, or Voucher Code...').fill('ORD-');
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-7/desktop-promotions-usage-ledger.png' });
});

test('task 8 marketplace direct subroutes retain source subnavigation and mapping label overlays', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  const marketplaceRoutes = [
    ['/admin/marketplace', 'Marketplace Ecosystem'],
    ['/admin/marketplace/auth', 'Marketplace Open Platform Authorization'],
    ['/admin/marketplace/oms', 'Marketplace Orders (OMS)'],
    ['/admin/marketplace/inventory', 'Marketplace Inventory & WMS Allocation'],
    ['/admin/marketplace/sync', 'Marketplace Webhooks & Closed-Loop Sync Stream'],
  ] as const;

  for (const [route, heading] of marketplaceRoutes) {
    await page.goto(route);
    await expect(page.getByText(heading, { exact: true }).first()).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1920);
  }

  await page.goto('/admin/marketplace/auth');
  await page.getByRole('button', { name: /Orders \(OMS\)/ }).click();
  await expect(page).toHaveURL('/admin/marketplace/oms');
  await expect(page.getByText('Marketplace Orders (OMS)', { exact: true }).first()).toBeVisible();

  await page.goto('/admin/marketplace/auth');
  await page.getByRole('button', { name: 'Connect New Store' }).click();
  await expect(page.getByText('Authorize New Marketplace Store', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.getByText('Authorize New Marketplace Store', { exact: true })).toBeHidden();
  await expect(page.getByText('Marketplace Open Platform Authorization', { exact: true })).toBeVisible();

  await page.goto('/admin/marketplace/inventory');
  await page.getByTitle('Configure Channel SKU Mapping').first().click();
  await expect(page.getByText('Channel SKU Mapping Matrix', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-marketplace-channel-mapping-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.getByText('Channel SKU Mapping Matrix', { exact: true })).toBeHidden();
  await expect(page.getByText('Marketplace Inventory & WMS Allocation', { exact: true })).toBeVisible();

  await page.goto('/admin/marketplace/oms');
  await page.getByTitle(/airway bill/i).first().click();
  await expect(page.getByText('Official Marketplace Airway Bill (AWB)', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-marketplace-shipping-label-modal.png' });
  await page.getByRole('button', { name: 'Close', exact: true }).click();
  await expect(page.getByText('Official Marketplace Airway Bill (AWB)', { exact: true })).toBeHidden();
  await expect(page.getByText('Marketplace Orders (OMS)', { exact: true }).first()).toBeVisible();
});

test('task 8 storefront CMS and header footer menu CRUD demos remain source-native', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/storefront');
  await expect(page.getByText('Navigation & Storefront CMS', { exact: true })).toBeVisible();
  await page.getByText('Open Page Builder', { exact: true }).click();
  await expect(page).toHaveURL('/admin/pages');

  await page.goto('/admin/menus/header');
  await expect(page.getByText('3-Level Header Menu Builder', { exact: true })).toBeVisible();
  const headerItemsBefore = await page.getByTitle('Edit Level 1 Menu').count();
  await page.getByRole('button', { name: 'Add Level 1 Menu' }).click();
  await page.getByPlaceholder('e.g. Electronics & Audio').fill('Task 8 Header CRUD');
  await page.getByRole('button', { name: 'Save Level 1' }).click();
  await expect(page.getByText('Task 8 Header CRUD', { exact: true }).first()).toBeVisible();
  await page.getByTitle('Delete Level 1 Menu').last().click();
  await expect(page.getByTitle('Edit Level 1 Menu')).toHaveCount(headerItemsBefore);

  await page.goto('/admin/menus/footer');
  await expect(page.getByText('Footer Navigation & Column Admin', { exact: true })).toBeVisible();
  const footerSectionsBefore = await page.getByText(/child links$/).count();
  await page.getByRole('button', { name: 'Add Footer Section' }).click();
  await page.getByPlaceholder('e.g. Customer Care & Support').fill('Task 8 Footer CRUD');
  await page.getByRole('button', { name: 'Save Section' }).click();
  const createdFooterHeading = page.locator('h3').filter({ hasText: 'Task 8 Footer CRUD' });
  await expect(createdFooterHeading).toBeVisible();
  await createdFooterHeading.locator('xpath=../../..').locator('button').last().click();
  await expect(page.getByText(/child links$/)).toHaveCount(footerSectionsBefore);
});

test('task 8 page builder directory and full editor retain source create duplicate delete and section operations', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/pages');
  const directory = page.locator('#page-admin-directory');
  await expect(directory).toBeVisible();
  const cardsBefore = await directory.locator('div.rounded-3xl.bg-white').count();
  await page.getByRole('button', { name: 'Create Layout Page' }).click();
  const builder = page.locator('#page-visual-builder');
  await expect(builder).toBeVisible();
  await page.getByRole('button', { name: 'Pages' }).click();
  await expect(directory).toBeVisible();
  await expect(directory.locator('div.rounded-3xl.bg-white')).toHaveCount(cardsBefore + 1);
  await page.getByTitle('Clone Page Layout').last().click();
  await expect(directory.locator('div.rounded-3xl.bg-white')).toHaveCount(cardsBefore + 2);
  await page.getByTitle('Delete Page').last().click();
  await expect(directory.locator('div.rounded-3xl.bg-white')).toHaveCount(cardsBefore + 1);

  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await expect(builder).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-editor-native.png' });
  const outlineItems = builder.locator('[title="Duplicate Section"]');
  const outlineCards = outlineItems.locator('xpath=../../..');
  const sectionsBefore = await outlineItems.count();
  const initialFirstTitle = await outlineCards.first().locator('p').first().textContent();
  const initialSecondTitle = await outlineCards.nth(1).locator('p').first().textContent();
  if (!initialFirstTitle || !initialSecondTitle) throw new Error('Expected two source section titles in the Outline.');
  await builder.getByTitle('Move Down').first().click();
  await expect(outlineCards.first().locator('p').first()).toHaveText(initialSecondTitle);
  await expect(outlineCards.nth(1).locator('p').first()).toHaveText(initialFirstTitle);
  const renderedSections = builder.locator('[class~="divide-y"]').first().locator(':scope > *');
  await expect(renderedSections.first()).toContainText(initialSecondTitle);
  await expect(renderedSections.nth(1)).toContainText(initialFirstTitle);
  await outlineItems.first().click();
  await expect(outlineItems).toHaveCount(sectionsBefore + 1);
  await builder.getByTitle('Delete Section').last().click();
  await expect(outlineItems).toHaveCount(sectionsBefore);

  const inspectorHeadline = 'Task 8 Inspector Persisted Headline';
  await builder.getByPlaceholder('Section main heading').fill(inspectorHeadline);
  await expect(renderedSections.first()).toContainText(inspectorHeadline);
  await builder.getByRole('button', { name: 'Preview' }).click();
  const previewModal = page.locator('div.fixed.inset-0.z-50').last();
  await expect(previewModal.getByRole('heading', { name: inspectorHeadline, exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-inspector-preview-persistence.png' });
  await page.keyboard.press('Escape');
  await expect(page.getByTitle('Close Preview (Esc)')).toBeHidden();

  await builder.getByRole('button', { name: 'Add Section' }).first().click();
  await expect(page.locator('#add-section-modal')).toBeVisible();
  const addSectionModal = page.locator('#add-section-modal');
  const commerceCategory = addSectionModal.getByRole('button', { name: 'Commerce & Shop', exact: true });
  await commerceCategory.click();
  await expect(commerceCategory).toHaveClass(/bg-indigo-600/);
  await expect(addSectionModal.locator('p').filter({ hasText: 'Showing 4 available modular sections.' })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-add-section-commerce-tab.png' });
  await addSectionModal.getByText('Insert Section', { exact: true }).first().click();
  await expect(outlineItems).toHaveCount(sectionsBefore + 1);
});

test('task 8 page builder viewport JSON outline inspector and preview layers retain native composition', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/pages');
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  const builder = page.locator('#page-visual-builder');
  await expect(builder.getByTitle('Desktop Full Screen')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-editor-native.png' });
  await builder.getByTitle('Tablet 768px').click();
  await expect(builder.locator('[class*="max-w-[768px]"]')).toBeVisible();
  await builder.getByTitle('Mobile 390px').click();
  await expect(builder.locator('[class*="max-w-[390px]"]')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-mobile-viewport.png' });
  await builder.getByTitle('Collapse Section Outline').click();
  await expect(builder.getByTitle('Expand Section Outline')).toBeVisible();
  await builder.getByTitle('Collapse Inspector').click();
  await expect(builder.getByTitle('Expand Section Inspector')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-panels-collapsed.png' });
  await builder.getByTitle('Expand Section Outline').click();
  await builder.getByTitle('Expand Section Inspector').click();

  await builder.getByTitle('Export / Import JSON Layout').click();
  await expect(page.getByText('Page Layout JSON Schema', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-json-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await builder.getByRole('button', { name: 'Preview' }).click();
  await expect(page.getByTitle('Close Preview (Esc)')).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-8/desktop-page-builder-preview-native.png' });
  await page.keyboard.press('Escape');
  await expect(page.getByTitle('Close Preview (Esc)')).toBeHidden();
});

test('task 9 Tax Zones retains its source tab ownership and inert Tax Class/Geo Zone add controls', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/settings/tax-zones');
  await expect(page.getByRole('button', { name: 'Add Tax Rate Rule' })).toBeVisible();
  await page.getByRole('button', { name: /Tax Classes \(4\)/ }).click();
  await page.getByRole('button', { name: 'Add Tax Class' }).click();
  await expect(page.locator('div.fixed.inset-0')).toHaveCount(0);
  await page.getByRole('button', { name: /Geo Tax Zones \(3\)/ }).click();
  await page.getByRole('button', { name: 'Add Geo Tax Zone' }).click();
  await expect(page.locator('div.fixed.inset-0')).toHaveCount(0);
});

test('task 9 every approved Settings URL preserves a mounted source surface at desktop and 390px', async ({ page }) => {
  const settingsRoutes = [
    ['/admin/settings', 'Store Settings'],
    ['/admin/settings/users', 'User Login Setting'],
    ['/admin/settings/shop', 'My Shop Setting'],
    ['/admin/settings/payment', 'Payment Gateways & Digital Wallets'],
    ['/admin/settings/points', 'Points & Rewards Setting'],
    ['/admin/settings/invoice', 'Invoice Information Setting'],
    ['/admin/settings/email', 'email Setting'],
    ['/admin/settings/social', 'Social Media Links'],
    ['/admin/settings/general', 'general Setting'],
    ['/admin/settings/couriers', 'Couriers Setting'],
    ['/admin/settings/agent-groups', 'agent group Setting'],
    ['/admin/settings/order-status', 'Orders Status Setting'],
    ['/admin/settings/countries', 'Countries Setting'],
    ['/admin/settings/zones', 'Zones Setting'],
    ['/admin/settings/currencies', 'Currencies Setting'],
    ['/admin/settings/order-total', 'Order Total Modules (order_total)'],
    ['/admin/settings/tax-zones', 'Tax Zones & SST Setting'],
    ['/admin/settings/ai-agent', 'AI Auto Reply'],
  ] as const;

  for (const [viewportName, viewport] of [
    ['desktop', { width: 1920, height: 900 }],
    ['mobile', { width: 390, height: 844 }],
  ] as const) {
    await page.setViewportSize(viewport);
    for (const [route, marker] of settingsRoutes) {
      await page.goto(route);
      await expect(page.getByText(marker, { exact: true }).first()).toBeVisible();
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(viewport.width);
      if (route === '/admin/settings/tax-zones') {
        await expect(page).toHaveURL('/admin/settings/tax-zones');
        await expect(page.locator('main')).toContainText('Tax Zones & SST Setting');
        await page.screenshot({ path: `../../output/admin-react-rebuild/task-9/${viewportName}-settings-tax-zones.png` });
      }
    }
  }
});

test('task 9 shipping tabs, badges, dense tables, overlays, and close states retain mock-only source behavior', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/shipping');
  const shipping = page.locator('main');
  await expect(shipping.getByRole('button', { name: 'Overview Hub' })).toBeVisible();
  await expect(shipping.getByRole('button', { name: /Rate Matrices & Postage Modes/ })).toContainText(/Rates/);
  await expect(shipping.getByRole('button', { name: /Fulfillment & Waybills/ })).toContainText(/\d/);

  await page.getByRole('button', { name: 'Schedule Driver Pickup' }).click();
  await expect(page.getByText('Book Courier Doorstep Pickup', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-shipping-pickup-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('#pickup-schedule-modal-overlay')).toHaveCount(0);
  await expect(shipping.getByRole('button', { name: 'Overview Hub' })).toBeVisible();

  await shipping.getByRole('button', { name: /Rate Matrices & Postage Modes/ }).click();
  await expect(page.getByText('General Shipping Rates Configuration', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Insert Shipping Rate' }).first().click();
  await expect(page.getByText('General Shipping Shipping Rate Insert', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-shipping-rate-modal.png' });
  await page.getByRole('button', { name: 'Back' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('General Shipping Rates Configuration', { exact: true })).toBeVisible();

  await shipping.getByRole('button', { name: /Smart Routing Engine/ }).click();
  await expect(page.getByText('Smart Courier Routing Rules Engine', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Add Routing Rule' }).click();
  await expect(page.getByText('New Smart Courier Routing Rule', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Smart Courier Routing Rules Engine', { exact: true })).toBeVisible();

  await shipping.getByRole('button', { name: /Courier Integrations/ }).click();
  await expect(page.getByText('Malaysian Courier API Integrations & Gateway Hub', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Configure Credentials' }).first().click();
  await expect(page.locator('div.fixed.inset-0').getByText(/API Integration$/, { exact: false })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-shipping-courier-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Malaysian Courier API Integrations & Gateway Hub', { exact: true })).toBeVisible();

  await shipping.getByRole('button', { name: /Fulfillment & Waybills/ }).click();
  await expect(page.locator('tbody tr')).toHaveCount(6);
  await page.getByTitle('Print 4x6 Thermal AWB').first().click();
  await expect(page.getByText('4x6" Thermal Airway Bill (Consignment Note)', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-shipping-thermal-awb-modal.png' });
  await page.getByRole('button', { name: 'Close' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByTitle('Print 4x6 Thermal AWB').first()).toBeVisible();
  await page.getByTitle('View Milestone Timeline').first().click();
  await expect(page.getByText(/UNASSIGNED-AWB|MY\d+/)).toBeVisible();
  await page.getByRole('button', { name: 'Close' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByTitle('View Milestone Timeline').first()).toBeVisible();

  await shipping.getByRole('button', { name: /Webhooks & Telemetry/ }).click();
  await expect(page.getByText('Live Courier Webhooks & Tracking Event Pipeline', { exact: true })).toBeVisible();
  await shipping.getByRole('button', { name: /Billing Reconciliation/ }).click();
  await expect(page.getByText('Courier Monthly Statement Reconciliation & Weight Discrepancy Audit', { exact: true })).toBeVisible();
  await page.getByTitle('Submit Dispute Ticket').first().click();
  await expect(page.getByText('Submit Courier Overcharge Dispute Claim', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Courier Monthly Statement Reconciliation & Weight Discrepancy Audit', { exact: true })).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1920);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-shipping-reconciliation.png' });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/admin/shipping');
  await expect(page.getByRole('button', { name: /Courier Integrations/ })).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/mobile-shipping-tabs.png' });
});

test('task 9 payment, staff, OSCommerce settings, and settings hub navigation preserve dense local source controls', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/settings/payment');
  await expect(page.getByTitle('Billplz')).toBeVisible();
  await expect(page.getByTitle('Stripe')).toBeVisible();
  await expect(page.getByTitle('Touch \'n Go eWallet')).toBeVisible();
  await page.getByRole('button', { name: 'Configure Keys' }).first().click();
  await expect(page.locator('div.fixed.inset-0').getByText(/^Configure /)).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-payment-gateway-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Payment Gateways & Digital Wallets', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: /Direct Bank Transfer \/ DuitNow/ }).click();
  await expect(page.getByText('Direct Bank Transfer & DuitNow QR', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Add Bank Account' }).click();
  await expect(page.getByText('Add Malaysian Bank Account', { exact: true })).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/desktop-payment-bank-modal.png' });
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Direct Bank Transfer & DuitNow QR', { exact: true })).toBeVisible();

  for (const [route, marker] of [
    ['/admin/settings/users', 'User Login Setting'],
    ['/admin/settings/order-status', 'Orders Status Setting'],
    ['/admin/settings/countries', 'Countries Setting'],
    ['/admin/settings/zones', 'Zones Setting'],
    ['/admin/settings/currencies', 'Currencies Setting'],
    ['/admin/settings/order-total', 'Order Total Modules (order_total)'],
    ['/admin/settings/tax-zones', 'Tax Zones & SST Setting'],
    ['/admin/settings/couriers', 'Couriers Setting'],
  ] as const) {
    await page.goto(route);
    await expect(page.getByText(marker, { exact: true }).first()).toBeVisible();
    await expect(page.locator('main')).toContainText(marker);
  }

  await page.goto('/admin/settings');
  await page.getByRole('button', { name: 'Countries Setting' }).click();
  await expect(page).toHaveURL('/admin/settings/countries');
  await expect(page.getByRole('heading', { name: 'Countries Setting', exact: true })).toBeVisible();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-9/mobile-settings-countries.png' });
});

test('task 9 staff and OSCommerce entities use real local create, permission, reset, delete, and editor states', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/settings/users');
  const staffRows = page.locator('tbody tr');
  const initialStaffCount = await staffRows.count();
  await page.getByRole('button', { name: 'New User +' }).click();
  await expect(page.getByRole('heading', { name: 'Add New User Login' })).toBeVisible();
  await page.getByRole('button', { name: 'Sales Role' }).click();
  await expect(page.getByText('Permission Control', { exact: true })).toBeVisible();
  await expect(page.locator('input[type="checkbox"]')).not.toHaveCount(0);
  await page.locator('#input-staff-username').fill('task9_operator');
  await page.locator('#input-staff-password').fill('Task9Pass!');
  await page.getByRole('button', { name: 'Create User' }).click();
  await expect(staffRows).toHaveCount(initialStaffCount + 1);
  const createdStaff = staffRows.filter({ hasText: 'task9_operator' });
  await expect(createdStaff).toBeVisible();
  await createdStaff.getByRole('button').click();
  await page.getByRole('button', { name: 'Reset Password' }).click();
  await expect(page.getByRole('heading', { name: 'Reset Password' })).toBeVisible();
  await page.getByPlaceholder('Enter new password (min 6 chars)').fill('NewTask9!');
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.getByRole('heading', { name: 'Reset Password' })).toHaveCount(0);
  await expect(createdStaff).toBeVisible();
  await createdStaff.getByRole('button').click();
  await page.getByRole('button', { name: 'Delete User' }).click();
  await expect(page.getByRole('heading', { name: 'Delete Staff Account' })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.getByRole('heading', { name: 'Delete Staff Account' })).toHaveCount(0);
  await createdStaff.getByRole('button').click();
  await page.getByRole('button', { name: 'Delete User' }).click();
  await page.getByRole('button', { name: 'Confirm Delete' }).click();
  await expect(staffRows).toHaveCount(initialStaffCount);
  await expect(createdStaff).toHaveCount(0);

  const entityStates = [
    ['/admin/settings/countries', 'Add Destination Country', 'Add New Destination Country', 'Edit Country Properties', 'Edit Country Configuration'],
    ['/admin/settings/zones', 'Add State / Zone', 'Add State / Province Zone', 'Edit Zone', 'Edit Administrative Zone'],
    ['/admin/settings/currencies', 'Add Currency', 'Add New Multi-Currency', 'Edit Currency Parameters', 'Edit Currency Parameters'],
    ['/admin/settings/order-status', 'Add Custom Status', 'Create Custom Order Status', 'Edit Status Parameters', 'Edit Order Status'],
  ] as const;

  for (const [route, addLabel, addHeading, editTitle, editHeading] of entityStates) {
    await page.goto(route);
    await page.getByRole('button', { name: addLabel }).click();
    await expect(page.getByText(addHeading, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Cancel' }).last().click();
    await expect(page.getByText(addHeading, { exact: true })).toHaveCount(0);
    await page.getByTitle(editTitle).first().click();
    await expect(page.getByText(editHeading, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Cancel' }).last().click();
    await expect(page.getByText(editHeading, { exact: true })).toHaveCount(0);
  }

  await page.goto('/admin/settings/order-total');
  await page.getByTitle('Edit Module Properties').first().click();
  await expect(page.getByText(/^Configure /, { exact: false })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel' }).last().click();
  await expect(page.locator('div.fixed.inset-0.z-50')).toHaveCount(0);
  await expect(page.getByText('Installed Order Total Modules & Pipeline Sort Sequence', { exact: true })).toBeVisible();
});

test('task 10 support keeps its three-column desktop queue, local filters, selected thread, draft, takeover, and context layers', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/support');

  const supportHub = page.locator('#customer-support-hub-container');
  const queue = page.locator('#conversation-queue-panel');
  const workspace = page.locator('#chat-workspace-container');
  const context = page.locator('#customer-context-panel');
  await expect(supportHub).toBeVisible();
  await expect(queue).toBeVisible();
  await expect(workspace).toBeVisible();
  await expect(context).toBeVisible();
  await expect(page.locator('#conversation-queue-list')).toHaveCSS('overflow-y', 'auto');
  await expect(workspace.locator('div.flex-1.overflow-y-auto').first()).toHaveCSS('overflow-y', 'auto');

  const queueItems = page.locator('[id^="conversation-item-"]');
  await expect(queueItems).toHaveCount(7);
  await page.getByRole('button', { name: 'WhatsApp', exact: true }).click();
  await expect(queueItems).toHaveCount(1);
  await expect(queueItems.first()).toContainText('Dato’ Kenneth Tan');
  await page.getByRole('button', { name: 'All Channels', exact: true }).click();
  await page.locator('#support-inbox-search').fill('Nurul');
  await expect(queueItems).toHaveCount(1);
  await queueItems.first().click();
  await expect(workspace.getByRole('heading', { name: 'Nurul Huda (Modest Luxe Boutique)' })).toBeVisible();
  await expect(page.locator('#ai-draft-recommendation-box')).toBeVisible();
  await expect(context).toContainText('B2B Wholesale Quotation & Tier Pricing');
  await expect(page.getByRole('button', { name: 'Take Over', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Take Over', exact: true }).click();
  await expect(page.getByTitle('Return conversation to automated AI handling')).toBeVisible();
  await expect(page.getByPlaceholder(/Reply to Nurul Huda/)).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/desktop-support-three-column-selected.png' });

  await page.getByTitle('Hide Customer Context').click();
  await expect(context).toHaveCount(0);
  await page.getByTitle('Show Customer Context').click();
  await expect(context).toBeVisible();
});

test('task 10 support retains the mobile list-to-thread composition without horizontal overflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/admin/support');
  await expect(page.locator('#conversation-queue-panel')).toBeVisible();
  await expect(page.locator('#chat-workspace-container')).toBeHidden();
  await page.locator('#conversation-item-conv-whatsapp-1').click();
  await expect(page.locator('#chat-workspace-container')).toBeVisible();
  await expect(page.locator('#conversation-queue-panel')).toBeHidden();
  await expect(page.locator('#chat-composer-container')).toContainText('Quick Responses:');
  await expect(page.getByPlaceholder(/Reply to Dato’ Kenneth Tan/)).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/mobile-support-thread.png' });
});

test('task 10 AI studio personality and knowledge tabs stay interactive while source Guardrails and Simulator defects remain explicit', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/ai-auto-reply');
  await expect(page.locator('#ai-personality-tab-container')).toBeVisible();
  await expect(page.getByText('AI Bot Identity & Profile', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: /^AI Knowledge Scope/ }).click();
  await expect(page.locator('#ai-knowledge-scope-tab-container')).toBeVisible();
  await expect(page.getByText('FAQ & Store Policies Knowledge Base', { exact: true })).toBeVisible();
  await page.getByPlaceholder('Search FAQ keywords...').fill('shipping');
  await expect(page.locator('#faq-knowledge-scope-card')).toContainText('shipping');
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/desktop-ai-personality-knowledge.png' });

  for (const tabName of ['AI Safety Guardrails', 'Interactive Simulator'] as const) {
    await page.goto('/admin/ai-auto-reply');
    const sourceError = page.waitForEvent('pageerror');
    await page.getByRole('button', { name: new RegExp(`^${tabName}`) }).click();
    await expect(sourceError).resolves.toMatchObject({ message: expect.stringContaining('blacklistedCompetitors') });
  }
});

test('task 10 reports cover every source tab, chart/table surface, label, and local-only controls', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/reports');
  await expect(page.getByRole('heading', { name: 'Enterprise Analytics & Reports Center' })).toBeVisible();
  await page.locator('#select-report-channel').selectOption('tiktok_shop');
  await page.locator('#select-report-daterange').selectOption('7d');

  const reportTabs = [
    ['sales', 'Revenue & Sales Trajectory', 'chart-table'],
    ['orders', 'Order Fulfillment Lifecycle & Funnel Efficiency', 'funnel'],
    ['products', 'SKU Performance, Profitability & Inventory Velocity', 'table'],
    ['customers', 'RFM (Recency, Frequency, Monetary) Customer Segmentation', 'table'],
    ['logistics', 'Malaysian Courier SLA Performance & Billing Audit', 'table'],
    ['marketing', 'Storefront E-Commerce Conversion Funnel (Last 30 Days)', 'table'],
    ['tax', 'Malaysian Sales & Service Tax (SST) Summary (Royal Malaysian Customs)', 'table'],
  ] as const;

  for (const [tab, marker, surface] of reportTabs) {
    await page.locator(`#tab-report-${tab}`).click();
    await expect(page.getByText(marker, { exact: true })).toBeVisible();
    if (surface === 'chart-table') {
      await expect(page.locator('svg.recharts-surface').first()).toBeVisible();
    }
    if (surface === 'chart-table' || surface === 'table') {
      await expect(page.locator('table').first()).toBeVisible();
    }
  }

  await expect(page.locator('#tab-report-tax')).toHaveClass(/bg-neutral-900/);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1920);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/desktop-reports-tax.png' });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/mobile-reports-tax.png' });
});

test('task 10 Developer Specs preserves master prompt, route content, and JSON schema navigation', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/admin/developer-specs');
  await expect(page.getByRole('heading', { name: 'Developer Specifications & Master Code Prompt' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Copy Master Prompt' })).toBeVisible();
  await page.getByRole('button', { name: 'Route & Component Specs', exact: true }).click();
  await expect(page.getByRole('columnheader', { name: 'Route Path' })).toBeVisible();
  await expect(page.locator('tbody tr').first()).toBeVisible();
  await page.getByRole('button', { name: 'JSON Schema Blueprints', exact: true }).click();
  await expect(page.getByText(/Schema Definition$/, { exact: false }).first()).toBeVisible();
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/desktop-developer-specs-schemas.png' });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-10/mobile-developer-specs-schemas.png' });
});
