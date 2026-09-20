<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminAppController;
use App\Http\Controllers\Admin\CatalogApiController;
use App\Http\Controllers\Admin\CatalogListingSettingsApiController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsFormContractsController;
use App\Http\Controllers\Admin\CmsMediaAssetController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\CommerceSettingsApiController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CouponApiController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerDirectoryDataController;
use App\Http\Controllers\Admin\DashboardDataController;
use App\Http\Controllers\Admin\EasyParcelWebhookReceiptApiController;
use App\Http\Controllers\Admin\MenuApiController;
use App\Http\Controllers\Admin\NotificationTemplateApiController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\OrderApiController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderNotificationApiController;
use App\Http\Controllers\Admin\PageApiController;
use App\Http\Controllers\Admin\PagePreviewController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductReviewApiController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\ShipmentApiController;
use App\Http\Controllers\Admin\ShippingProviderApiController;
use App\Http\Controllers\Admin\StaffReviewPermissionController;
use App\Http\Controllers\Admin\StorefrontShellSettingsApiController;
use App\Http\Controllers\Admin\TaxClassApiController;
use App\Http\Controllers\Admin\TaxSettingsApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerCouponController;
use App\Http\Controllers\CustomerPaymentProofController;
use App\Http\Controllers\CustomerProductReviewController;
use App\Http\Controllers\CustomerReturnRequestController;
use App\Http\Controllers\Admin\ReturnRequestApiController;
use App\Http\Controllers\EasyParcelWebhookController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductReviewMediaController;
use App\Http\Controllers\Storefront\CmsContactSubmissionController;
use App\Http\Controllers\Storefront\NewsletterSubscriptionController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StorefrontCurrencyController;
use App\Http\Controllers\StorefrontPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('storefront.index');
Route::get('/categories/{category:slug}', [StorefrontController::class, 'category'])->name('storefront.categories.show');
Route::get('/collections/{collection:slug}', [StorefrontController::class, 'collection'])->name('storefront.collections.show');
Route::get('/products/{handle}', [StorefrontController::class, 'show'])->name('storefront.products.show');
Route::post('/currency', StorefrontCurrencyController::class)->name('storefront.currency.update');
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'))
    ->name('storefront.csrf-token');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::get('/cart/drawer', [CartController::class, 'drawer'])->name('cart.drawer');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout/shipping-quote', [CheckoutController::class, 'shippingQuote'])->name('checkout.shipping-quote');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/payment-return/{payment}', [CheckoutController::class, 'paymentReturn'])->name('checkout.payment-return');
Route::post('/checkout/payment-retry/{payment}', [CheckoutController::class, 'retryHostedPayment'])->name('checkout.payment-retry');
Route::post('/payments/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('payments.webhooks.stripe');
Route::post('/payments/webhooks/billplz', [PaymentWebhookController::class, 'billplz'])->name('payments.webhooks.billplz');
Route::post('/payments/webhooks/paypal', [PaymentWebhookController::class, 'paypal'])->name('payments.webhooks.paypal');
Route::post('/webhooks/easyparcel', EasyParcelWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.easyparcel');
Route::post('/webhooks/easyparcel/{token}', [EasyParcelWebhookController::class, 'legacy'])
    ->middleware('throttle:60,1')
    ->name('webhooks.easyparcel.legacy');
Route::get('/order/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/orders/{order}/payments/{payment}/proof', [CustomerPaymentProofController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('customer.payment-proofs.store');
Route::get('/order-tracking', [OrderTrackingController::class, 'preview'])->name('order-tracking.show');
Route::redirect('/order-tracking/preview', '/order-tracking');
Route::post('/order-tracking/request', [OrderTrackingController::class, 'requestCode'])
    ->middleware('throttle:order-tracking')->name('order-tracking.request');
Route::post('/order-tracking/verify', [OrderTrackingController::class, 'verify'])
    ->middleware('throttle:order-tracking')->name('order-tracking.verify');
Route::post('/order-tracking/resend', [OrderTrackingController::class, 'resend'])
    ->middleware('throttle:order-tracking')->name('order-tracking.resend');
Route::get('/pages/{slug}', StorefrontPageController::class)->name('storefront.pages.show');
Route::post('/cms/forms/contact/{page}/{stage}/{section}', CmsContactSubmissionController::class)
    ->whereIn('stage', ['draft', 'published'])
    ->middleware(['signed:relative', 'throttle:cms-contact'])
    ->name('cms.forms.contact.submit');
Route::post('/cms/forms/newsletter/{page}/{stage}/{section}', NewsletterSubscriptionController::class)
    ->whereIn('stage', ['draft', 'published'])
    ->middleware(['signed:relative', 'throttle:cms-newsletter'])
    ->name('cms.forms.newsletter.submit');

Route::middleware('guest:customer')->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/login', [CustomerAuthController::class, 'create'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'store'])->name('login.store');
    Route::get('/register', [CustomerAuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [CustomerAuthController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [CustomerAuthController::class, 'sendPasswordResetLink'])->name('password.email');
});
Route::middleware(['auth:customer', 'customer'])->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/', [CustomerAccountController::class, 'show'])->name('account');
    Route::post('/coupons/apply', [CustomerCouponController::class, 'apply'])->name('coupons.apply');
    Route::patch('/profile', [CustomerAccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/addresses', [CustomerAccountController::class, 'storeAddress'])->name('addresses.store');
    Route::post('/wishlist/{product}', [CustomerAccountController::class, 'storeWishlist'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [CustomerAccountController::class, 'destroyWishlist'])->name('wishlist.destroy');
    Route::get('/reviews/media/{media}', [ProductReviewMediaController::class, 'showForCustomer'])->name('reviews.media.show');
    Route::post('/reviews', [CustomerProductReviewController::class, 'store'])->name('reviews.store');
    Route::post('/returns', [CustomerReturnRequestController::class, 'store'])->name('returns.store');
    Route::get('/preview/reviews', [CustomerProductReviewController::class, 'preview'])->name('reviews.preview');
    Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/admin/login', [AuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'store'])->name('admin.login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/admin/shipping/easyparcel/callback', [ShippingProviderApiController::class, 'callback'])
    ->name('admin.shipping.easyparcel.callback');
Route::get('/admin/shipping/easyparcel/callback/denied', [ShippingProviderApiController::class, 'callbackDenied'])
    ->name('admin.shipping.easyparcel.callback.denied');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/api/dashboard', DashboardDataController::class)->name('api.dashboard');
    Route::get('/api/customers', [CustomerDirectoryDataController::class, 'index'])->name('api.customers');
    Route::get('/api/staff/review-permissions', [StaffReviewPermissionController::class, 'index'])->name('api.staff.review-permissions.index');
    Route::put('/api/staff/{staff}/review-permissions', [StaffReviewPermissionController::class, 'update'])->name('api.staff.review-permissions.update');
    Route::get('/api/reviews', [ProductReviewApiController::class, 'index'])->name('api.reviews.index');
    Route::get('/api/reviews/media/{media}', [ProductReviewMediaController::class, 'showForAdmin'])->name('api.reviews.media.show');
    Route::get('/api/reviews/{review}/audit', [ProductReviewApiController::class, 'audit'])->name('api.reviews.audit');
    Route::post('/api/reviews/{review}/moderate', [ProductReviewApiController::class, 'moderate'])->name('api.reviews.moderate');
    Route::post('/api/reviews/bulk-moderate', [ProductReviewApiController::class, 'bulkModerate'])->name('api.reviews.bulk-moderate');
    Route::post('/api/customers', [CustomerDirectoryDataController::class, 'store']);
    Route::patch('/api/customers/{customer}', [CustomerDirectoryDataController::class, 'update']);
    Route::delete('/api/customers/{customer}', [CustomerDirectoryDataController::class, 'destroy']);
    Route::get('/api/pages', [PageApiController::class, 'index'])->name('api.pages.index');
    Route::post('/api/media-assets', [CmsMediaAssetController::class, 'store'])->name('api.media-assets.store');
    Route::post('/api/pages', [PageApiController::class, 'store'])->name('api.pages.store');
    Route::get('/api/pages/{page}/draft', [PageApiController::class, 'showDraft'])->name('api.pages.draft.show');
    Route::get('/api/pages/{page}/form-contracts', CmsFormContractsController::class)->name('api.pages.form-contracts');
    Route::patch('/api/pages/{page}/draft', [PageApiController::class, 'updateDraft'])->name('api.pages.draft.update');
    Route::patch('/api/pages/{page}', [PageApiController::class, 'update'])->name('api.pages.update');
    Route::delete('/api/pages/{page}', [PageApiController::class, 'destroy'])->name('api.pages.destroy');
    Route::post('/api/pages/{page}/duplicate', [PageApiController::class, 'duplicate'])->name('api.pages.duplicate');
    Route::post('/api/pages/{page}/publish', [PageApiController::class, 'publish'])->name('api.pages.publish');
    Route::post('/api/pages/{page}/homepage', [PageApiController::class, 'publish'])->name('api.pages.homepage');
    Route::post('/api/pages/{page}/unpublish', [PageApiController::class, 'unpublish'])->name('api.pages.unpublish');
    Route::post('/api/pages/{page}/archive', [PageApiController::class, 'archive'])->name('api.pages.archive');
    Route::get('/pages/{page}/preview', PagePreviewController::class)->name('pages.preview');
    Route::get('/cms-canary/pages/{page}', [StorefrontPageController::class, 'forPublished'])->name('cms-canary.pages.show');
    Route::get('/api/catalog', [CatalogApiController::class, 'index'])->name('api.catalog.index');
    Route::get('/api/catalog-listing-settings', [CatalogListingSettingsApiController::class, 'show'])->name('api.catalog-listing-settings.show');
    Route::put('/api/catalog-listing-settings', [CatalogListingSettingsApiController::class, 'update'])->name('api.catalog-listing-settings.update');
    Route::post('/api/catalog/categories', [CatalogApiController::class, 'storeCategory']);
    Route::patch('/api/catalog/categories/{category}', [CatalogApiController::class, 'updateCategory']);
    Route::delete('/api/catalog/categories/{category}', [CatalogApiController::class, 'destroyCategory']);
    Route::post('/api/catalog/collections', [CatalogApiController::class, 'storeCollection']);
    Route::patch('/api/catalog/collections/{collection}', [CatalogApiController::class, 'updateCollection']);
    Route::delete('/api/catalog/collections/{collection}', [CatalogApiController::class, 'destroyCollection']);
    Route::post('/api/catalog/products', [CatalogApiController::class, 'storeProduct']);
    Route::patch('/api/catalog/products/{product}', [CatalogApiController::class, 'updateProduct']);
    Route::delete('/api/catalog/products/{product}', [CatalogApiController::class, 'destroyProduct']);
    Route::get('/api/menus', [MenuApiController::class, 'index'])->name('api.menus.index');
    Route::put('/api/menus/{key}', [MenuApiController::class, 'update'])->whereIn('key', ['header', 'footer']);
    Route::get('/api/storefront-shell-settings', [StorefrontShellSettingsApiController::class, 'show'])->name('api.storefront-shell-settings.show');
    Route::put('/api/storefront-shell-settings', [StorefrontShellSettingsApiController::class, 'update'])->name('api.storefront-shell-settings.update');
    Route::get('/api/orders', [OrderApiController::class, 'index'])->name('api.orders.index');
    Route::post('/api/returns/{returnRequest}/approve', [ReturnRequestApiController::class, 'approve']);
    Route::post('/api/returns/{returnRequest}/reject', [ReturnRequestApiController::class, 'reject']);
    Route::post('/api/returns/{returnRequest}/receive', [ReturnRequestApiController::class, 'receive']);
    Route::post('/api/returns/{returnRequest}/resolve-refund', [ReturnRequestApiController::class, 'resolveRefund']);
    Route::post('/api/returns/{returnRequest}/resolve-replacement', [ReturnRequestApiController::class, 'resolveReplacement']);
    Route::get('/api/notification-templates', [NotificationTemplateApiController::class, 'index'])->name('api.notification-templates.index');
    Route::put('/api/notification-templates/{eventKey}', [NotificationTemplateApiController::class, 'update'])->name('api.notification-templates.update');
    Route::post('/api/notification-templates/reset', [NotificationTemplateApiController::class, 'reset'])->name('api.notification-templates.reset');
    Route::post('/api/notification-templates/{eventKey}/test', [NotificationTemplateApiController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('api.notification-templates.test');
    Route::get('/api/orders/{order}/notification-dispatches', [OrderNotificationApiController::class, 'index'])->name('api.orders.notification-dispatches.index');
    Route::post('/api/orders/{order}/notifications/preview', [OrderNotificationApiController::class, 'preview'])->name('api.orders.notifications.preview');
    Route::post('/api/orders/{order}/notifications/send-current', [OrderNotificationApiController::class, 'sendCurrent'])->name('api.orders.notifications.send-current');
    Route::post('/api/notification-dispatches/{dispatch:public_id}/retry', [OrderNotificationApiController::class, 'retry'])->name('api.notification-dispatches.retry');
    Route::post('/api/notification-dispatches/{dispatch:public_id}/send-now', [OrderNotificationApiController::class, 'sendNow'])->name('api.notification-dispatches.send-now');
    Route::post('/api/notification-dispatches/{dispatch:public_id}/resend', [OrderNotificationApiController::class, 'resend'])->name('api.notification-dispatches.resend');
    Route::get('/api/orders/{order}/payments/{payment}/proof', [OrderApiController::class, 'showPaymentProof'])->name('api.orders.payments.proof.show');
    Route::get('/api/orders/{order}/payments/{payment}/proof/download', [OrderApiController::class, 'downloadPaymentProof'])->name('api.orders.payments.proof.download');
    Route::post('/api/orders/{order}/payments/{payment}/proof/review', [OrderApiController::class, 'reviewPaymentProof'])->name('api.orders.payments.proof.review');
    Route::patch('/api/orders/{order}/status', [OrderApiController::class, 'updateStatus']);
    Route::patch('/api/orders/{order}/tracking', [OrderApiController::class, 'tracking']);
    Route::patch('/api/orders/{order}/delivery-details', [OrderApiController::class, 'updateDeliveryDetails'])
        ->name('api.orders.delivery-details.update');
    Route::post('/api/shipping/orders/{order}/dispatch', [ShipmentApiController::class, 'dispatch']);
    Route::post('/api/shipping/orders/{order}/easyparcel/quotes', [ShipmentApiController::class, 'quoteEasyParcel'])
        ->name('api.shipping.easyparcel.quotes.store');
    Route::post('/api/shipping/orders/{order}/easyparcel/bookings', [ShipmentApiController::class, 'bookEasyParcel'])
        ->name('api.shipping.easyparcel.bookings.store');
    Route::post('/api/shipping/orders/{order}/easyparcel/reconciliation', [ShipmentApiController::class, 'reconcileEasyParcel'])
        ->name('api.shipping.easyparcel.reconciliation.store');
    Route::get('/api/shipping/shipments', [ShipmentApiController::class, 'index'])->name('api.shipping.shipments.index');
    Route::post('/api/shipping/shipments/{shipment}/corrections', [ShipmentApiController::class, 'startCorrection'])
        ->name('api.shipping.shipments.corrections.store');
    Route::patch('/api/shipping/shipments/{shipment}/operational-details', [ShipmentApiController::class, 'updateOperationalDetails'])
        ->name('api.shipping.shipments.operational-details.update');
    Route::get('/api/shipping/shipments/{shipment}/label', [ShipmentApiController::class, 'showEasyParcelLabel'])
        ->name('api.shipping.shipments.label.show');
    Route::get('/api/shipping/shipments/{shipment}/label/download', [ShipmentApiController::class, 'downloadEasyParcelLabel'])
        ->name('api.shipping.shipments.label.download');
    Route::post('/api/shipping/shipments/{shipment}/easyparcel/status', [ShipmentApiController::class, 'refreshEasyParcelStatus'])
        ->name('api.shipping.shipments.easyparcel.status.store');
    Route::post('/api/shipping/shipments/{shipment}/easyparcel/recovery', [ShipmentApiController::class, 'confirmEasyParcelProviderFailure'])
        ->name('api.shipping.shipments.easyparcel.recovery.store');
    Route::get('/api/shipping/webhook-receipts', EasyParcelWebhookReceiptApiController::class)->name('api.shipping.webhook-receipts.index');
    Route::post('/api/shipping/shipments/{shipment}/events', [ShipmentApiController::class, 'appendEvent']);
    Route::get('/api/shipping/providers/easyparcel', [ShippingProviderApiController::class, 'show'])->name('api.shipping.providers.easyparcel.show');
    Route::put('/api/shipping/providers/easyparcel', [ShippingProviderApiController::class, 'update'])->name('api.shipping.providers.easyparcel.update');
    Route::delete('/api/shipping/providers/easyparcel', [ShippingProviderApiController::class, 'disconnect'])->name('api.shipping.providers.easyparcel.disconnect');
    Route::post('/api/shipping/providers/easyparcel/authorize', [ShippingProviderApiController::class, 'beginAuthorization'])->name('api.shipping.providers.easyparcel.authorize');
    Route::post('/api/orders/{order}/refund', [OrderApiController::class, 'refund']);
    Route::get('/api/coupons', [CouponApiController::class, 'index'])->name('api.coupons.index');
    Route::post('/api/coupons', [CouponApiController::class, 'store']);
    Route::patch('/api/coupons/{coupon}', [CouponApiController::class, 'update']);
    Route::delete('/api/coupons/{coupon}', [CouponApiController::class, 'destroy']);
    Route::get('/api/commerce-settings', [CommerceSettingsApiController::class, 'show'])->name('api.commerce-settings.show');
    Route::get('/api/shop-branding', [\App\Http\Controllers\Admin\ShopBrandingController::class, 'show']);
    Route::put('/api/shop-branding', [\App\Http\Controllers\Admin\ShopBrandingController::class, 'update']);
    Route::put('/api/commerce-settings', [CommerceSettingsApiController::class, 'update']);
    Route::put('/api/commerce-settings/bank-transfer', [CommerceSettingsApiController::class, 'updateBankTransfer']);
    Route::put('/api/commerce-settings/cod', [CommerceSettingsApiController::class, 'updateCod']);
    Route::put('/api/commerce-settings/shipping', [CommerceSettingsApiController::class, 'updateShipping']);
    Route::put('/api/commerce-settings/return-policy', [CommerceSettingsApiController::class, 'updateReturnPolicy']);
    Route::get('/api/tax-classes', [TaxClassApiController::class, 'index'])->name('api.tax-classes.index');
    Route::post('/api/tax-classes', [TaxClassApiController::class, 'store'])->name('api.tax-classes.store');
    Route::patch('/api/tax-classes/{taxClass}', [TaxClassApiController::class, 'update'])->name('api.tax-classes.update');
    Route::post('/api/tax-classes/{taxClass}/activate', [TaxClassApiController::class, 'activate'])->name('api.tax-classes.activate');
    Route::post('/api/tax-classes/{taxClass}/deactivate', [TaxClassApiController::class, 'deactivate'])->name('api.tax-classes.deactivate');
    Route::delete('/api/tax-classes/{taxClass}', [TaxClassApiController::class, 'destroy'])->name('api.tax-classes.destroy');
    Route::get('/api/tax-settings', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.index');
    Route::post('/api/tax-settings/preview', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.preview');
    Route::put('/api/tax-settings/configuration', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.configuration.update');
    Route::post('/api/tax-settings/zones', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.zones.store');
    Route::patch('/api/tax-settings/zones/{taxZone}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.zones.update');
    Route::delete('/api/tax-settings/zones/{taxZone}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.zones.destroy');
    Route::post('/api/tax-settings/classes', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.classes.store');
    Route::patch('/api/tax-settings/classes/{taxClass}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.classes.update');
    Route::delete('/api/tax-settings/classes/{taxClass}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.classes.destroy');
    Route::post('/api/tax-settings/rules', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.rules.store');
    Route::patch('/api/tax-settings/rules/{taxRule}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.rules.update');
    Route::delete('/api/tax-settings/rules/{taxRule}', [TaxSettingsApiController::class, 'retired'])->name('api.tax-settings.rules.destroy');
    Route::get('/', AdminAppController::class)->name('dashboard');
    Route::get('/commerce', AdminAppController::class)->name('commerce');
    Route::get('/orders', AdminAppController::class)->name('orders.index');
    Route::get('/customers', AdminAppController::class)->name('customers.index');
    Route::get('/shipping', AdminAppController::class)->name('shipping');
    Route::get('/catalog', AdminAppController::class)->name('catalog');
    Route::get('/catalog/products', AdminAppController::class)->name('products.index');
    Route::get('/catalog/categories', AdminAppController::class)->name('categories.index');
    Route::get('/catalog/collections', AdminAppController::class)->name('collections.index');
    Route::get('/promotions', AdminAppController::class)->name('coupons.index');
    Route::get('/api/marketplace', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'index']);
    Route::get('/api/inventory/movements', [\App\Http\Controllers\Admin\InventoryMovementController::class, 'index']);
    Route::get('/api/inventory/physical-movements', [\App\Http\Controllers\Admin\InventoryMovementController::class, 'physical']);
    Route::get('/api/inventory/obligations', [\App\Http\Controllers\Admin\InventoryObligationController::class, 'index']);
    Route::get('/api/inventory/balances/{balance}/obligations', [\App\Http\Controllers\Admin\InventoryObligationController::class, 'balance'])->whereNumber('balance');
    Route::get('/api/inventory/balances', [\App\Http\Controllers\Admin\PhysicalBalanceController::class, 'index']);
    Route::post('/api/inventory/balances', [\App\Http\Controllers\Admin\PhysicalBalanceController::class, 'store']);
    Route::get('/api/inventory/warehouses', [\App\Http\Controllers\Admin\InventoryWarehouseController::class, 'index']);
    Route::get('/api/inventory/fulfillment-routes', [\App\Http\Controllers\Admin\InventoryRoutingController::class, 'index']);
    Route::get('/api/inventory/route-stores', [\App\Http\Controllers\Admin\InventoryRoutingController::class, 'stores']);
    Route::get('/api/inventory/order-activations', [\App\Http\Controllers\Admin\ManagedInventoryActivationController::class, 'index']);
    Route::post('/api/inventory/order-activations', [\App\Http\Controllers\Admin\ManagedInventoryActivationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/api/inventory/readiness', \App\Http\Controllers\Admin\InventoryReadinessController::class);
    Route::post('/api/inventory/basic/configuration', [\App\Http\Controllers\Admin\BasicWmsConfigurationController::class, 'store']);
    Route::post('/api/inventory/basic/safety', [\App\Http\Controllers\Admin\BasicWmsSafetyController::class, 'store']);
    Route::get('/api/inventory/basic/safety-history', [\App\Http\Controllers\Admin\BasicWmsSafetyController::class, 'index']);
    Route::post('/api/inventory/sandbox-attempts', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/api/inventory/sandbox-attempts', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'index']);
    Route::get('/api/inventory/sandbox-options', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'options']);
    Route::post('/api/inventory/sandbox-attempts/{attempt}/dispatch', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'dispatch'])->whereUuid('attempt')->middleware('throttle:10,1');
    Route::get('/api/inventory/sandbox-attempts/{attempt}', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'show'])->whereUuid('attempt');
    Route::post('/api/inventory/sandbox-attempts/{attempt}/recheck', [\App\Http\Controllers\Admin\SandboxInventoryAttemptController::class, 'recheck'])->whereUuid('attempt')->middleware('throttle:10,1');
    Route::post('/api/inventory/mode-reviews', [\App\Http\Controllers\Admin\InventoryModeReviewController::class,'store']);
    Route::put('/api/inventory/fulfillment-routes', [\App\Http\Controllers\Admin\InventoryRoutingController::class, 'save']);
    Route::get('/api/inventory/transfers', [\App\Http\Controllers\Admin\InventoryTransferController::class, 'index']);
    Route::get('/api/inventory/transfer-options', [\App\Http\Controllers\Admin\PhysicalBalanceController::class, 'transferOptions']);
    Route::post('/api/inventory/transfers', [\App\Http\Controllers\Admin\InventoryTransferController::class, 'store']);
    Route::post('/api/inventory/transfers/{transfer}/transition', [\App\Http\Controllers\Admin\InventoryTransferController::class, 'transition'])->whereNumber('transfer');
    Route::post('/api/inventory/warehouses', [\App\Http\Controllers\Admin\InventoryWarehouseController::class, 'store']);
    Route::put('/api/inventory/warehouses/{warehouse}', [\App\Http\Controllers\Admin\InventoryWarehouseController::class, 'update'])->whereNumber('warehouse');
    Route::post('/api/inventory/count-reviews', [\App\Http\Controllers\Admin\PhysicalCountController::class, 'review']);
    Route::post('/api/inventory/adjustment-reviews', [\App\Http\Controllers\Admin\PhysicalCountController::class, 'adjustment']);
    Route::post('/api/inventory/reconciliations', [\App\Http\Controllers\Admin\InventoryReconciliationController::class, 'store']);
    Route::post('/api/inventory/count-reviews/{review}/confirm', [\App\Http\Controllers\Admin\PhysicalCountController::class, 'confirm'])->whereUuid('review');
    Route::post('/api/inventory/store-management/reviews', [\App\Http\Controllers\Admin\StoreInventoryReviewController::class, 'store']);
    Route::post('/api/inventory/store-management/reviews/{review}/confirm', [\App\Http\Controllers\Admin\StoreInventoryReviewController::class, 'confirm'])->whereUuid('review');
    Route::get('/api/inventory/store-management/overview', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'overview']);
    Route::get('/api/inventory/store-management/stores', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'stores']);
    Route::get('/api/inventory/store-management/history', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'history']);
    Route::get('/api/inventory/store-management/operations/{operation}', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'operation'])->whereUuid('operation');
    Route::post('/api/inventory/store-management/sync-all', [\App\Http\Controllers\Admin\StoreInventorySyncController::class, 'syncAll']);
    Route::post('/api/inventory/store-management/sync-tasks/{task}/retry', [\App\Http\Controllers\Admin\StoreInventorySyncController::class, 'retry'])->whereUuid('task');
    Route::get('/api/inventory/store-management/stores/{store}/readiness', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'readiness'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/takeover', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'takeover'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/pause', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'pause'])->whereNumber('store');
    Route::post('/api/inventory/store-management/mode', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'mode']);
    Route::get('/api/marketplace/connections', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'connections']);
    Route::get('/api/marketplace/oauth-result', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'result']);
    Route::post('/api/marketplace/stores/{store}/lazada-test-access', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'renewOfficialTestAccess'])->whereNumber('store')->middleware('throttle:3,1');
    Route::get('/marketplace/oauth-acceptance', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'acceptance']);
    Route::get('/marketplace/orders/{order}/preview', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'preview'])->whereNumber('order');
    Route::get('/marketplace/inventory-preview-33', function () {
        abort_unless(app()->environment('local'), 404);
        $path = public_path('build-marketplace33-preview/manifest.json');
        abort_unless(is_file($path), 503, 'Build the isolated Inventory preview first.');
        $manifest = json_decode(file_get_contents($path), true);
        $entry = $manifest['src/previews/marketplace33/main.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.marketplace33-preview', ['entry'=>$entry])->header('Cache-Control', 'no-store');
    });
    Route::get('/marketplace/inventory-preview-39', function () {
        abort_unless(app()->environment('local'), 404);
        $path = public_path('build-inventory39-preview/manifest.json');
        abort_unless(is_file($path), 503, 'Build the isolated ZIP39 Inventory preview first.');
        $manifest = json_decode(file_get_contents($path), true);
        $entry = $manifest['src/previews/inventory39/main.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.inventory39-preview', ['entry'=>$entry])->header('Cache-Control', 'no-store');
    });
    Route::get('/marketplace/oauth-preview', function (\Illuminate\Http\Request $request) {
        abort_unless(app()->environment('local'), 404);
        $path = public_path('build-marketplace-preview/manifest.json');
        abort_unless(is_file($path), 503, 'Build the isolated Marketplace OAuth preview first.');
        $manifest = json_decode(file_get_contents($path), true);
        $entry = $manifest['src/previews/marketplace30/oauth.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.marketplace-oauth-preview', ['entry'=>$entry, 'bootstrap'=>[
            'user'=>['name'=>$request->user()->name, 'email'=>$request->user()->email, 'avatar'=>null],
            'csrfToken'=>csrf_token(), 'logoutUrl'=>route('logout'),
        ]])->header('Cache-Control', 'no-store');
    });
    Route::middleware(\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read')->group(function () {
    Route::get('/api/marketplace/inventory', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'index']);
    Route::get('/api/marketplace/inventory/exceptions', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'exceptions']);
    Route::get('/api/marketplace/inventory/reconciliation', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'reconciliation']);
    Route::get('/api/marketplace/publishing-tasks/{task}', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'show'])->whereNumber('task');
    Route::post('/api/marketplace/publishing-tasks/{task}/recovery-authorizations', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'authorizeRecovery'])->whereNumber('task')->middleware('throttle:10,1');
    Route::post('/api/marketplace/publishing-tasks/{task}/reconcile', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'reconcile'])->whereNumber('task')->middleware('throttle:3,1');
    Route::get('/api/marketplace/stores/{store}/publishing-status', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'storeStatus'])->whereNumber('store');
    Route::get('/api/marketplace/products/{product}/publishing-source', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'source'])->whereNumber('product');
    Route::get('/api/marketplace/stores/{store}/publishing-request/{key}', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'recover'])->whereNumber('store')->whereUuid('key');
    Route::get('/api/marketplace/catalog', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'index']);
    Route::get('/api/marketplace/stores/{store}/listing-attribute-values', [\App\Http\Controllers\Admin\MarketplaceListingMetadataController::class, 'attributeValues'])->whereNumber('store')->middleware('throttle:10,1');
    Route::get('/api/marketplace/stores/{store}/listing-categories', [\App\Http\Controllers\Admin\MarketplaceListingMetadataController::class, 'categories'])->whereNumber('store')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read', 'throttle:10,1']);
    Route::get('/api/marketplace/stores/{store}/listing-attributes', [\App\Http\Controllers\Admin\MarketplaceListingMetadataController::class, 'attributes'])->whereNumber('store')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read', 'throttle:10,1']);
    Route::get('/api/marketplace/stores/{store}/listing-brands', [\App\Http\Controllers\Admin\MarketplaceListingMetadataController::class, 'brands'])->whereNumber('store')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read', 'throttle:10,1']);
    Route::get('/api/marketplace/stores/{store}/listing-logistics', [\App\Http\Controllers\Admin\MarketplaceListingMetadataController::class, 'logistics'])->whereNumber('store')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read', 'throttle:10,1']);
    Route::get('/api/marketplace/catalog/summary', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'summary']);
    Route::get('/api/marketplace/listings/{listing}/mapping-context', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'mappingContext'])->whereNumber('listing');
    Route::get('/api/marketplace/catalog/products', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'products']);
    Route::get('/api/marketplace/orders', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'index']);
    Route::get('/api/marketplace/orders/summary', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'summary']);
    Route::post('/api/marketplace/orders/{order}/pack', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'pack'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}/fulfillment-attempts/{attempt}', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'fulfillmentAttempt'])->whereNumber(['order','attempt']);
    Route::post('/api/marketplace/orders/{order}/fulfillment-attempts/{attempt}/reconcile', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'reconcileRts'])->whereNumber(['order','attempt'])->middleware('throttle:10,1');
    Route::post('/api/marketplace/orders/{order}/ready-to-ship', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'readyToShip'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}/label', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'label'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'show'])->whereNumber('order');
    Route::post('/api/marketplace/stores/{store}/orders-fetch', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'fetch'])->whereNumber('store')->middleware('throttle:10,1');
    Route::get('/api/marketplace/order-runs/{run}', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'run'])->whereNumber('run');
    Route::post('/api/marketplace/order-runs/{run}/retry', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'retry'])->whereNumber('run')->middleware('throttle:10,1');
    Route::post('/api/marketplace/stores/{store}/catalog-fetch', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'fetch'])->whereNumber('store')->middleware('throttle:10,1');
    Route::get('/api/marketplace/stores/{store}/catalog-runs/latest', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'latest'])->whereNumber('store');
    Route::post('/api/marketplace/stores/{store}/stock-sync-tasks', [\App\Http\Controllers\Admin\MarketplaceStockSyncController::class, 'stage'])->whereNumber('store')->middleware('throttle:10,1');
    Route::get('/api/marketplace/stock-sync-tasks/{task}', [\App\Http\Controllers\Admin\MarketplaceStockSyncController::class, 'show'])->whereNumber('task');
    Route::post('/api/marketplace/stock-sync-tasks/{task}/dispatch', [\App\Http\Controllers\Admin\MarketplaceStockSyncController::class, 'dispatch'])->whereNumber('task')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':stock', 'throttle:10,1']);
    Route::get('/api/marketplace/catalog-runs/{run}', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'show'])->whereNumber('run');
    Route::post('/api/marketplace/catalog-runs/{run}/retry', [\App\Http\Controllers\Admin\MarketplaceCatalogController::class, 'retry'])->whereNumber('run')->middleware('throttle:10,1');
    });
    Route::post('/api/marketplace/products/{product}/attribute-images', \App\Http\Controllers\Admin\MarketplaceAttributeImageController::class)->whereNumber('product')
        ->name('api.marketplace.attribute-images.store');
    Route::post('/api/marketplace/listings/{listing}/import-product', [\App\Http\Controllers\Admin\MarketplaceImportController::class, 'product'])->whereNumber('listing')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':import', 'throttle:10,1']);
    Route::post('/api/marketplace/listings/{listing}/import-variant', [\App\Http\Controllers\Admin\MarketplaceImportController::class, 'variant'])->whereNumber('listing')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':import', 'throttle:10,1']);
    Route::post('/api/marketplace/stores/{store}/publishing-tasks', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'stage'])->whereNumber('store')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':publish', 'throttle:30,1,marketplace-publish-stage:']);
    Route::post('/api/marketplace/stores/{store}/products/{product}/images/{image}/prepare', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'prepareImage'])
        ->whereNumber(['store','product','image'])->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':publish', 'throttle:5,1']);
    Route::post('/api/marketplace/publishing-tasks/{task}/dispatch', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'dispatch'])->whereNumber('task')
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':publish', 'throttle:5,1']);
    Route::post('/api/marketplace/shopee/authorize', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'start'])->middleware('throttle:10,1');
    Route::post('/api/marketplace/lazada/authorize', [\App\Http\Controllers\Admin\LazadaOAuthController::class, 'start'])->middleware('throttle:10,1');
    Route::get('/marketplace/oauth/lazada/callback', [\App\Http\Controllers\Admin\LazadaOAuthController::class, 'callback'])->middleware('throttle:20,1');
    Route::post('/api/marketplace/stores/{store}/disconnect', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'disconnect'])->whereNumber('store');
    Route::get('/marketplace/oauth/shopee/callback', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'callback'])->middleware('throttle:20,1');
    // Legacy endpoint retained for any authorization sessions created before OAuth callbacks were normalized.
    Route::get('/marketplace/shopee/callback', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'callback'])->middleware('throttle:20,1');
    Route::post('/api/marketplace/stores', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'save']);
    Route::patch('/api/marketplace/stores/{store}', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'save'])->whereNumber('store');
    Route::delete('/api/marketplace/stores/{store}', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'destroy'])->whereNumber('store');
    Route::put('/api/marketplace/listings/{listing}/mapping', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'mapping'])->whereNumber('listing');
    Route::delete('/api/marketplace/listings/{listing}/mapping', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'unlink'])->whereNumber('listing');
    Route::post('/api/marketplace/remote/{operation}', [\App\Http\Controllers\Admin\MarketplaceApiController::class, 'unavailable'])->where('operation','fetch|publish|sync|import|authorize');
    Route::get('/marketplace/mapping', AdminAppController::class)->name('marketplace.mapping');
    Route::get('/marketplace', AdminAppController::class)->name('marketplace');
    Route::get('/marketplace/auth', AdminAppController::class)->name('marketplace.auth');
    Route::get('/marketplace/oms', AdminAppController::class)->name('marketplace.oms');
    Route::get('/marketplace/inventory', AdminAppController::class)->name('marketplace.inventory');
    Route::get('/marketplace/sync', AdminAppController::class)->name('marketplace.sync');
    Route::get('/storefront', AdminAppController::class)->name('storefront');
    Route::get('/storefront/header-announcement', AdminAppController::class)->name('storefront.header-announcement');
    Route::get('/pages', AdminAppController::class)->name('pages.index');
    Route::get('/menus/header', AdminAppController::class)->name('menus.index');
    Route::get('/menus/footer', AdminAppController::class)->name('ui.menus.footer');
    Route::get('/support', AdminAppController::class)->name('support');
    Route::get('/ai-auto-reply', AdminAppController::class)->name('ai-auto-reply');
    Route::get('/reports', AdminAppController::class)->name('reports');
    Route::get('/reports/sales', AdminAppController::class)->name('reports.sales');
    Route::get('/reports/orders', AdminAppController::class)->name('reports.orders');
    Route::get('/reports/products', AdminAppController::class)->name('reports.products');
    Route::get('/reports/customers', AdminAppController::class)->name('reports.customers');
    Route::get('/reports/shipping', AdminAppController::class)->name('reports.shipping');
    Route::get('/reports/tax', AdminAppController::class)->name('reports.tax');
    Route::get('/settings', AdminAppController::class)->name('settings.index');
    Route::get('/settings/users', AdminAppController::class)->name('account.edit');
    Route::get('/settings/shop', AdminAppController::class)->name('settings.shop');
    Route::get('/settings/payment', AdminAppController::class)->name('settings.payment');
    Route::get('/settings/points', AdminAppController::class)->name('settings.points');
    Route::get('/settings/invoice', AdminAppController::class)->name('settings.invoice');
    Route::get('/settings/email', AdminAppController::class)->name('settings.email');
    Route::get('/settings/social', AdminAppController::class)->name('social-links.index');
    Route::get('/settings/general', AdminAppController::class)->name('settings.general');
    Route::get('/settings/couriers', AdminAppController::class)->name('settings.couriers');
    Route::get('/settings/agent-groups', AdminAppController::class)->name('settings.agent-groups');
    Route::get('/settings/order-status', AdminAppController::class)->name('settings.order-status');
    Route::get('/settings/countries', AdminAppController::class)->name('settings.countries');
    Route::get('/settings/zones', AdminAppController::class)->name('settings.zones');
    Route::get('/settings/currencies', AdminAppController::class)->name('settings.currencies');
    Route::get('/settings/order-total', AdminAppController::class)->name('settings.order-total');
    Route::redirect('/settings/tax-zones', '/admin/settings/tax-classes')->name('settings.tax-zones');
    Route::get('/settings/tax-classes', AdminAppController::class)->name('settings.tax-classes');
    Route::get('/settings/ai-agent', AdminAppController::class)->name('settings.ai-agent');
    Route::get('/developer-specs', AdminAppController::class)->name('developer-specs');
    Route::get('/search', SearchController::class)->name('search');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::patch('/orders/{order}/shipment', [OrderController::class, 'dispatch'])->name('orders.shipment');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/variants', [ProductController::class, 'storeVariant'])->name('products.variants.store');
    Route::post('/products/{product}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
    Route::patch('/product-images/{productImage}', [ProductController::class, 'updateImage'])->name('product-images.update');
    Route::delete('/product-images/{productImage}', [ProductController::class, 'destroyImage'])->name('product-images.destroy');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::patch('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::patch('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
    Route::post('/collections/{collection}/products', [CollectionController::class, 'addProduct'])->name('collections.products.store');
    Route::post('/menus', [ContentController::class, 'storeMenu'])->name('menus.store');
    Route::post('/menus/{menu}/items', [ContentController::class, 'storeMenuItem'])->name('menus.items.store');
    Route::patch('/menu-items/{menuItem}', [ContentController::class, 'updateMenuItem'])->name('menu-items.update');
    Route::delete('/menu-items/{menuItem}', [ContentController::class, 'destroyMenuItem'])->name('menu-items.destroy');
    Route::post('/settings', [OperationsController::class, 'storeSetting'])->name('settings.store');
    Route::post('/social-links', [OperationsController::class, 'storeSocialLink'])->name('social-links.store');
    Route::patch('/social-links/{socialLink}', [OperationsController::class, 'updateSocialLink'])->name('social-links.update');
    Route::delete('/social-links/{socialLink}', [OperationsController::class, 'destroySocialLink'])->name('social-links.destroy');
});
