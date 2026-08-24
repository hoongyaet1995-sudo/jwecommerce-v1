import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const fixture = resolve(testDirectory, '../../../public/images/source/jwstudio_logo_1786766390670.jpg');
const screenshot = resolve(testDirectory, '../../../test-results/catalog-media-acceptance/catalog-media-consumers.png');
const productTitle = 'Catalog Acceptance Product';
const categoryTitle = 'Catalog Acceptance Category';
const collectionTitle = 'Catalog Acceptance Collection';

test('catalog images upload, persist, and are consumed by all admin catalog surfaces', async ({page}) => {
  await page.goto('/admin/login');
  await page.locator('#email').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_EMAIL!);
  await page.locator('#password').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_PASSWORD!);
  await page.getByRole('button', {name: 'Sign in to Admin'}).click();
  await expect(page).toHaveURL(/\/admin/);

  await page.goto('/admin/catalog');
  const catalog = await page.evaluate(async () => {
    const response = await fetch('/admin/api/catalog', {credentials: 'same-origin'});
    return response.json() as Promise<{products: Array<{status: string; totalInventory: number; variants: Array<{enabled: boolean}>}>; categories: Array<{parentId: string | null}>; collections: Array<{type: string}>}>;
  });
  const inStock = catalog.products.filter(product => product.status === 'active' && product.totalInventory > 0).length;
  const activeSkus = catalog.products.filter(product => product.status === 'active').reduce((total, product) => total + product.variants.filter(variant => variant.enabled).length, 0);
  const nestedCategories = catalog.categories.filter(category => category.parentId !== null).length;
  const dynamicCollections = catalog.collections.filter(collection => collection.type === 'dynamic').length;
  await expect(page.getByText(`${catalog.products.length} Master Products`)).toBeVisible();
  await expect(page.getByText(`${activeSkus} Active SKUs`)).toBeVisible();
  await expect(page.getByText(`${inStock} active in stock`)).toBeVisible();
  await expect(page.getByText(`${nestedCategories} nested categories`, {exact: true})).toBeVisible();
  await expect(page.getByText(`${dynamicCollections} auto-rule powered`)).toBeVisible();

  await page.goto('/admin/catalog/products');
  const productRow = page.locator('tr', {hasText: productTitle});
  await expect(productRow).toBeVisible();
  await productRow.getByTitle('Edit Product & Matrix').click();
  await page.getByRole('button', {name: /3\. Media Gallery/}).click();
  const productUpload = page.waitForResponse(response => response.url().includes('/admin/api/media-assets') && response.request().method() === 'POST');
  await page.locator('input[type="file"]').setInputFiles(fixture);
  const uploadResponse = await productUpload;
  await expect(uploadResponse.status()).toBe(201);
  await expect(page.getByText('2 / 12 Photos')).toBeVisible();
  const uploadedProductImage = page.getByRole('img', {name: 'Catalog Acceptance Product - View #2'});
  await uploadedProductImage.locator('..').hover();
  await page.getByRole('button', {name: 'Make Primary'}).click();
  await page.getByRole('button', {name: 'Save Product & SKUs'}).click();
  await expect(page.getByText(`Product: ${productTitle}`)).toHaveCount(0);
  await expect(productRow.locator('img')).toHaveAttribute('src', /\/storage\/cms\/media\//);

  await page.goto('/admin/catalog/categories');
  const categoryRow = page.locator('tr', {hasText: categoryTitle});
  await categoryRow.locator('button').first().click();
  const categoryUpload = page.getByRole('button', {name: 'Upload'});
  await categoryUpload.locator('..').locator('input[type="file"]').setInputFiles(fixture);
  await expect(page.locator('input[placeholder="https://images.unsplash.com/..."]')).toHaveValue(/\/storage\/cms\/media\//);
  await page.getByRole('button', {name: 'Save Category'}).click();
  await expect(categoryRow.locator('img')).toHaveAttribute('src', /\/storage\/cms\/media\//);

  await page.goto('/admin/catalog/collections');
  const collectionCard = page.locator('div.group').filter({has: page.getByText(collectionTitle, {exact: true})});
  await collectionCard.getByRole('button').first().click();
  const collectionUpload = page.getByRole('button', {name: 'Upload'});
  await collectionUpload.locator('..').locator('input[type="file"]').setInputFiles(fixture);
  await expect(page.locator('input[placeholder="https://images.unsplash.com/..."]')).toHaveValue(/\/storage\/cms\/media\//);
  await expect(page.getByText(productTitle).last().locator('..').locator('img')).toHaveAttribute('src', /\/storage\/cms\/media\//);
  await page.getByRole('button', {name: 'Save Collection'}).click();
  await expect(collectionCard.getByRole('img', {name: collectionTitle})).toHaveAttribute('src', /\/storage\/cms\/media\//);

  await page.screenshot({path: screenshot, fullPage: true});
});

test('product deletion requires an explicit confirmation and cancel keeps the product intact', async ({page}) => {
  await page.goto('/admin/login');
  await page.locator('#email').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_EMAIL!);
  await page.locator('#password').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_PASSWORD!);
  await page.getByRole('button', {name: 'Sign in to Admin'}).click();

  await page.goto('/admin/catalog/products');
  const productRow = page.locator('tr', {hasText: productTitle});
  await expect(productRow).toBeVisible();
  await productRow.getByTitle('Delete Product').click();

  const confirmation = page.getByRole('dialog', {name: /delete product/i});
  await expect(confirmation).toBeVisible();
  await expect(confirmation.getByText(productTitle, {exact: true})).toBeVisible();
  await confirmation.getByRole('button', {name: /cancel/i}).click();
  await expect(confirmation).toHaveCount(0);
  await expect(productRow).toBeVisible();
});

test('PDP rich content templates persist through the catalog API and a reopened product editor', async ({page}) => {
  await page.goto('/admin/login');
  await page.locator('#email').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_EMAIL!);
  await page.locator('#password').fill(process.env.CATALOG_ACCEPTANCE_ADMIN_PASSWORD!);
  await page.getByRole('button', {name: 'Sign in to Admin'}).click();

  await page.goto('/admin/catalog/products');
  const productRow = page.locator('tr', {hasText: productTitle});
  await expect(productRow).toBeVisible();
  await productRow.getByTitle('Edit Product & Matrix').click();

  await page.getByRole('button', {name: /4\. PDP Rich Content & Specs/}).click();
  await page.getByRole('button', {name: 'Load Luxury Template'}).click();
  await expect(page.locator('input[value="Crafted from Japanese Beta-Titanium & Precision Hardware"]')).toBeVisible();
  await page.getByRole('button', {name: 'Save Product & SKUs'}).click();
  await expect(page.getByText(`Product: ${productTitle}`)).toHaveCount(0);

  await productRow.getByTitle('Edit Product & Matrix').click();
  await page.getByRole('button', {name: /4\. PDP Rich Content & Specs/}).click();
  await expect(page.locator('input[value="Crafted from Japanese Beta-Titanium & Precision Hardware"]')).toBeVisible();
  await expect(page.getByRole('button', {name: '5. SEO & Social Meta'})).toBeVisible();
});
