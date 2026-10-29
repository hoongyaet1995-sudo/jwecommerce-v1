<?php

Route::get('/webhooks/facebook/messenger', [\App\Http\Controllers\MessengerWebhookController::class, 'verify']);
Route::post('/webhooks/facebook/messenger', [\App\Http\Controllers\MessengerWebhookController::class, 'handle']);

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
Route::post('/api/social/hub/challenge',[\App\Http\Controllers\SocialHubReceiverController::class,'receive'])->middleware('throttle:60,1');
Route::post('/api/social/hub/events',[\App\Http\Controllers\SocialHubReceiverController::class,'receive'])->middleware('throttle:60,1');
Route::post('/api/social/hub/instagram',[\App\Http\Controllers\SocialHubReceiverController::class,'receive']);
Route::post('/api/social/hub/whatsapp',[\App\Http\Controllers\SocialHubReceiverController::class,'receive']);
Route::post('/api/social/hub/pair',\App\Http\Controllers\SocialHubPairingController::class)->middleware('throttle:5,1');
Route::middleware(['auth','admin'])->group(function(){
 Route::get('/admin/social-hub/installation',fn()=>abort(404));
 Route::get('/admin/api/social-hub/installation',[\App\Http\Controllers\Admin\SocialHubInstallationController::class,'index']);
 Route::put('/admin/api/social-hub/installation',fn()=>abort(404));
 Route::post('/admin/api/social-hub/heartbeat',[\App\Http\Controllers\Admin\SocialHubInstallationController::class,'heartbeat'])->middleware('throttle:10,1');
});


Route::post('/api/marketplace/hub/refresh', \App\Http\Controllers\MarketplaceHubRefreshController::class)
    ->middleware('throttle:30,1');
Route::post('/api/marketplace/hub/verify-shopee', \App\Http\Controllers\MarketplaceHubShopeeVerificationController::class)
    ->middleware('throttle:10,1');
Route::post('/api/marketplace/hub/events', \App\Http\Controllers\MarketplaceHubEventController::class)
    ->middleware('throttle:120,1');

Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/support/provider-image/{attachment}', [\App\Http\Controllers\SupportAttachmentController::class, 'provider'])->whereUuid('attachment')->middleware(['signed','throttle:120,1'])->name('support.provider-image');
Route::post('/support/chat/attachments', [\App\Http\Controllers\SupportAttachmentController::class, 'upload'])->middleware('throttle:20,1,support-upload:');
Route::get('/support/chat/attachments/{attachment}', [\App\Http\Controllers\SupportAttachmentController::class, 'show'])->whereUuid('attachment')->middleware('throttle:60,1,support-attachment-view:');
Route::get('/support/chat', [\App\Http\Controllers\Storefront\SupportChatController::class, 'show'])->middleware('throttle:60,1,support-poll:');
Route::get('/support/faq', [\App\Http\Controllers\SupportFaqController::class, 'show'])->middleware('throttle:60,1');
Route::post('/support/chat/messages', [\App\Http\Controllers\Storefront\SupportChatController::class, 'send'])->middleware('throttle:20,1,support-send:');
Route::post('/support/chat/resolved', [\App\Http\Controllers\Storefront\SupportChatController::class, 'resolved'])->middleware('throttle:20,1,support-resolved:');
Route::get('/page-template-sample/home-deco-catalog-preview', function () {
    abort_unless(app()->environment('local'), 404);
    $products = \App\Models\Category::where('slug', 'tables')->firstOrFail()->products()->where('status', 'active')->with(['variants', 'images'])->get();
    return view('storefront.samples.home-deco-catalog', compact('products'));
});

Route::get('/page-template-sample/home-deco-pdp/{handle}', function (\Illuminate\Http\Request $request, string $handle) {
    abort_unless(app()->environment('local'), 404);
    $view = app(StorefrontController::class)->show($request, $handle);
    if ($view instanceof \Illuminate\Http\RedirectResponse) return $view;
    return view('storefront.samples.home-deco-pdp', $view->getData());
});

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
    Route::post('/forgot-password', [CustomerAuthController::class, 'sendPasswordResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [CustomerAuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth:customer', 'customer'])->prefix('account')->name('customer.')->group(function (): void {
    Route::get('/', [CustomerAccountController::class, 'show'])->name('account');
    Route::post('/coupons/apply', [CustomerCouponController::class, 'apply'])->name('coupons.apply');
    Route::patch('/profile', [CustomerAccountController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/password', [\App\Http\Controllers\CustomerPasswordController::class, 'update'])->middleware('throttle:5,1')->name('security.update');
    Route::post('/addresses', [CustomerAccountController::class, 'storeAddress'])->name('addresses.store');
    Route::patch('/addresses/{address}', [CustomerAccountController::class, 'updateAddress'])->whereNumber('address')->name('addresses.update');
    Route::delete('/addresses/{address}', [CustomerAccountController::class, 'destroyAddress'])->whereNumber('address')->name('addresses.destroy');
    Route::post('/wishlist/{product}', [CustomerAccountController::class, 'storeWishlist'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [CustomerAccountController::class, 'destroyWishlist'])->name('wishlist.destroy');
    Route::get('/reviews/media/{media}', [ProductReviewMediaController::class, 'showForCustomer'])->name('reviews.media.show');
    Route::post('/reviews', [CustomerProductReviewController::class, 'store'])->name('reviews.store');
    Route::post('/returns', [CustomerReturnRequestController::class, 'store'])->name('returns.store');
    Route::get('/preview/reviews', [CustomerProductReviewController::class, 'preview'])->name('reviews.preview');
    Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
});

Route::get('/products/{handle}/enquire', [\App\Http\Controllers\ProductEnquiryController::class,'create'])->name('enquiries.create');
Route::post('/enquiries', [\App\Http\Controllers\ProductEnquiryController::class,'store'])->middleware('throttle:10,1,enquiry-submit:')->name('enquiries.store');
Route::get('/enquiries/{enquiry}', [\App\Http\Controllers\ProductEnquiryController::class,'receipt'])->whereUuid('enquiry')->middleware('throttle:30,1,enquiry-receipt:')->name('enquiries.receipt');
Route::post('/enquiries/{enquiry}/support', [\App\Http\Controllers\ProductEnquiryController::class,'support'])->whereUuid('enquiry')->middleware('throttle:10,1,enquiry-support:')->name('enquiries.support');
Route::post('/quotations/{quotationVersion}/accept',[\App\Http\Controllers\QuotationCheckoutController::class,'accept'])->whereUuid('quotationVersion')->middleware('throttle:10,1,quotation-accept:')->name('quotations.accept');
Route::get('/quotations/{quotationVersion}/checkout',[\App\Http\Controllers\QuotationCheckoutController::class,'checkout'])->whereUuid('quotationVersion')->name('quotations.checkout');
Route::post('/quotations/{quotationVersion}/checkout',[\App\Http\Controllers\QuotationCheckoutController::class,'store'])->whereUuid('quotationVersion')->middleware('throttle:10,1,quotation-checkout:')->name('quotations.checkout.store');
Route::get('/quotations/{quotationVersion}/payment',[\App\Http\Controllers\QuotationCheckoutController::class,'payment'])->whereUuid('quotationVersion')->name('quotations.payment');
Route::post('/quotations/{quotationVersion}/payment',[\App\Http\Controllers\QuotationCheckoutController::class,'pay'])->whereUuid('quotationVersion')->middleware('throttle:10,1,quotation-pay:')->name('quotations.pay');
Route::post('/quotations/{quotationVersion}/proof',[\App\Http\Controllers\QuotationCheckoutController::class,'proof'])->whereUuid('quotationVersion')->middleware('throttle:10,1,quotation-proof:')->name('quotations.proof');
Route::get('/quotations/{quotationVersion}', [\App\Http\Controllers\EnquiryQuotationController::class,'show'])->whereUuid('quotationVersion')->middleware('throttle:60,1,quotation-view:')->name('quotations.show');
Route::post('/quotations/{quotationVersion}/support', [\App\Http\Controllers\EnquiryQuotationController::class,'support'])->whereUuid('quotationVersion')->middleware('throttle:10,1,quotation-support:')->name('quotations.support');

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
    Route::get('/api/search/settings', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'settings']);
    Route::put('/api/search/settings', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'saveSettings']);
    Route::get('/api/search/refresh', [\App\Http\Controllers\Admin\SearchVisibilityController::class, 'refreshSeo'])->middleware('throttle:60,1,search-refresh-status:');
    Route::post('/api/search/refresh', [\App\Http\Controllers\Admin\SearchVisibilityController::class, 'refreshSeo'])->middleware('throttle:20,1,search-refresh-start:');
    Route::post('/api/search/refresh/{id}/advance', [\App\Http\Controllers\Admin\SearchVisibilityController::class, 'advanceRefresh'])->whereNumber('id')->middleware('throttle:120,1,search-refresh-advance:');
    Route::match(['GET','POST'],'/api/search/pages/{id}/review',[\App\Http\Controllers\Admin\SearchVisibilityController::class,'review'])->whereNumber('id');
    Route::get('/api/search/catalog', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'catalog']);
    Route::get('/api/search/tasks', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'tasks']);
    Route::post('/api/search/tasks/{task}/apply', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'apply']);
    Route::post('/api/search/batch/{kind}', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'batch'])->middleware('throttle:10,1');
    Route::get('/api/search/{kind}/{id}', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'entity'])->whereNumber('id');
    Route::put('/api/search/{kind}/{id}', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'update'])->whereNumber('id');
    Route::post('/api/search/{kind}/{id}/optimize', [\App\Http\Controllers\Admin\SearchVisibilityController::class,'optimize'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/api/enquiries', [\App\Http\Controllers\Admin\ProductEnquiryController::class,'index']);
    Route::get('/api/enquiries/{enquiry}', [\App\Http\Controllers\Admin\ProductEnquiryController::class,'show'])->whereUuid('enquiry');
    Route::patch('/api/enquiries/{enquiry}', [\App\Http\Controllers\Admin\ProductEnquiryController::class,'update'])->whereUuid('enquiry')->middleware('throttle:60,1');
    Route::post('/api/enquiries/{enquiry}/notes', [\App\Http\Controllers\Admin\ProductEnquiryController::class,'note'])->whereUuid('enquiry')->middleware('throttle:30,1');

    Route::get('/social/facebook/authorize', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'authorize'])->middleware('throttle:10,1');
    Route::get('/social/facebook/callback', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'callback'])->middleware('throttle:20,1');
    Route::get('/social/facebook/hub-return', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'hubReturn'])->middleware('throttle:20,1');
    Route::get('/api/social/facebook', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'index'])->middleware('throttle:30,1');
    Route::post('/api/social/facebook/select', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'select'])->middleware('throttle:10,1');
    Route::post('/api/social/facebook/{id}/check', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'check'])->middleware('throttle:30,1');
    Route::post('/api/social/facebook/{id}/disconnect', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'disconnect'])->middleware('throttle:10,1');
    Route::delete('/api/social/facebook/{id}', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'destroy'])->middleware('throttle:10,1');
    Route::get('/api/support/alerts', [\App\Http\Controllers\Admin\SupportAlertController::class, 'index']);
    Route::patch('/api/support/alerts/{id}/read', [\App\Http\Controllers\Admin\SupportAlertController::class, 'read']);
    Route::get('/api/support/improvement', [\App\Http\Controllers\Admin\SupportImprovementController::class,'index']);
    Route::get('/api/support/improvement/drafts', [\App\Http\Controllers\Admin\SupportImprovementController::class,'drafts']);
    Route::post('/api/support/improvement/drafts', [\App\Http\Controllers\Admin\SupportImprovementController::class,'store'])->middleware('throttle:10,1');
    Route::patch('/api/support/improvement/drafts/{draft}', [\App\Http\Controllers\Admin\SupportImprovementController::class,'update'])->whereNumber('draft')->middleware('throttle:20,1');
    Route::post('/api/support/improvement/drafts/{draft}/publish', [\App\Http\Controllers\Admin\SupportImprovementController::class,'publish'])->whereNumber('draft')->middleware('throttle:10,1');
    Route::post('/api/support/improvement/drafts/{draft}/dismiss', [\App\Http\Controllers\Admin\SupportImprovementController::class,'dismiss'])->whereNumber('draft')->middleware('throttle:20,1');
    Route::get('/api/support/faq', [\App\Http\Controllers\SupportFaqController::class, 'index']);
    Route::put('/api/support/faq', [\App\Http\Controllers\SupportFaqController::class, 'save'])->middleware('throttle:20,1');
    Route::get('/api/integration/ai', [\App\Http\Controllers\Admin\AiConnectionController::class, 'index']);
    Route::put('/api/integration/ai/{provider}', [\App\Http\Controllers\Admin\AiConnectionController::class, 'save'])->middleware('throttle:ai-provider-save');
    Route::post('/api/integration/ai/{provider}/verify', [\App\Http\Controllers\Admin\AiConnectionController::class, 'verify'])->middleware('throttle:ai-provider-check');
    Route::post('/api/social/facebook/{id}/inbound', [\App\Http\Controllers\Admin\FacebookAccountController::class, 'inbound'])->middleware('throttle:10,1');
    Route::post('/api/support/conversations/{conversation}/attachments', [\App\Http\Controllers\SupportAttachmentController::class, 'upload'])->whereUuid('conversation')->middleware('throttle:20,1');
    Route::post('/api/support/attachments/{attachment}/retry',[\App\Http\Controllers\SupportAttachmentController::class,'retry'])->whereUuid('attachment')->middleware('throttle:6,1');
    Route::post('/api/support/attachments/{attachment}/analysis/retry',[\App\Http\Controllers\SupportAttachmentController::class,'retryAnalysis'])->whereUuid('attachment')->middleware('throttle:3,1');
    Route::get('/api/support/attachments/{attachment}', [\App\Http\Controllers\SupportAttachmentController::class, 'show'])->whereUuid('attachment');
    Route::get('/api/support/conversations/{conversation}/memories', [\App\Http\Controllers\Admin\SupportCustomerMemoryController::class,'index'])->whereUuid('conversation');
    Route::patch('/api/support/conversations/{conversation}/memories/{memory}', [\App\Http\Controllers\Admin\SupportCustomerMemoryController::class,'update'])->whereUuid('conversation')->middleware('throttle:20,1');
    Route::delete('/api/support/conversations/{conversation}/memories/{memory}', [\App\Http\Controllers\Admin\SupportCustomerMemoryController::class,'destroy'])->whereUuid('conversation')->middleware('throttle:20,1');
    Route::get('/api/enquiries/{enquiry}/quotation',[\App\Http\Controllers\Admin\EnquiryQuotationController::class,'show'])->whereUuid('enquiry');
    Route::put('/api/enquiries/{enquiry}/quotation',[\App\Http\Controllers\Admin\EnquiryQuotationController::class,'save'])->whereUuid('enquiry')->middleware('throttle:30,1,quotation-save:');
    Route::post('/api/enquiries/{enquiry}/quotation/send',[\App\Http\Controllers\Admin\EnquiryQuotationController::class,'send'])->whereUuid('enquiry')->middleware('throttle:10,1,quotation-send:');
    Route::post('/api/enquiries/{enquiry}/quotation/revise',[\App\Http\Controllers\Admin\EnquiryQuotationController::class,'revise'])->whereUuid('enquiry')->middleware('throttle:10,1,quotation-revise:');
    Route::get('/enquiries/{enquiry}/quotation-preview',[\App\Http\Controllers\EnquiryQuotationController::class,'preview'])->whereUuid('enquiry')->name('quotations.preview');
    Route::get('/quotations/{quotationVersion}/preview',[\App\Http\Controllers\EnquiryQuotationController::class,'previewVersion'])->whereUuid('quotationVersion')->name('quotations.version-preview');
    Route::post('/enquiries/{enquiry}/quotation/send',[\App\Http\Controllers\EnquiryQuotationController::class,'send'])->whereUuid('enquiry')->middleware('throttle:10,1,quotation-send:')->name('quotations.send');
    Route::get('/api/support/conversations', [\App\Http\Controllers\Admin\SupportChatController::class, 'index']);
    Route::post('/api/support/conversations/{conversation}/messages', [\App\Http\Controllers\Admin\SupportChatController::class, 'send'])->whereUuid('conversation')->middleware('throttle:60,1');
    Route::patch('/api/support/conversations/{conversation}/status', [\App\Http\Controllers\Admin\SupportChatController::class, 'status'])->whereUuid('conversation');
    Route::patch('/api/support/conversations/{conversation}/pin', [\App\Http\Controllers\Admin\SupportChatController::class, 'pin'])->whereUuid('conversation');
    Route::patch('/api/support/conversations/{conversation}/archive', [\App\Http\Controllers\Admin\SupportChatController::class, 'archive'])->whereUuid('conversation');
    Route::post('/api/support/conversations/{conversation}/read', [\App\Http\Controllers\Admin\SupportChatController::class, 'read'])->whereUuid('conversation');
    Route::post('/api/support/conversations/{conversation}/notes', [\App\Http\Controllers\Admin\SupportChatController::class, 'note'])->whereUuid('conversation')->middleware('throttle:30,1');
    Route::get('/api/dashboard', DashboardDataController::class)->name('api.dashboard');
    Route::get('/api/customers', [CustomerDirectoryDataController::class, 'index'])->name('api.customers');
    Route::get('/api/staff/marketing-permissions', [\App\Http\Controllers\Admin\StaffMarketingPermissionController::class, 'index'])->name('api.staff.marketing-permissions.index');
    Route::put('/api/staff/{staff}/marketing-permissions', [\App\Http\Controllers\Admin\StaffMarketingPermissionController::class, 'update'])->middleware('throttle:30,1');
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
    Route::get('/api/cms/payment-methods', \App\Http\Controllers\Admin\CmsPaymentInformationController::class);
    Route::get('/api/cms/palettes', [\App\Http\Controllers\Admin\CmsPaletteLibraryController::class, 'index']);
    Route::post('/api/cms/palettes', [\App\Http\Controllers\Admin\CmsPaletteLibraryController::class, 'save'])->middleware('throttle:30,1');
    Route::get('/api/cms/brand-context', fn(\App\Services\CmsThemePalette $brand)=>response()->json(['data'=>$brand->current()]));
    Route::get('/api/cms/ai-workspaces/{page}', [\App\Http\Controllers\Admin\CmsAiWorkspaceController::class,'show']);
    Route::put('/api/cms/ai-workspaces/{page}', [\App\Http\Controllers\Admin\CmsAiWorkspaceController::class,'save']);
    Route::delete('/api/cms/ai-workspaces/{page}', [\App\Http\Controllers\Admin\CmsAiWorkspaceController::class,'discard']);
    Route::get('/api/cms/ai-tasks/{id}', [\App\Http\Controllers\Admin\CmsAiWorkspaceController::class,'task']);
    Route::get('/api/cms/ai-generator', [\App\Http\Controllers\Admin\CmsAiGeneratorController::class,'metadata']);
    Route::post('/api/cms/ai-generator', \App\Http\Controllers\Admin\CmsAiGeneratorController::class)->middleware(['throttle:12,1',\App\Http\Middleware\CmsAiTask::class]);
    Route::get('/api/pages', [PageApiController::class, 'index'])->name('api.pages.index');
    Route::post('/api/media-assets', [CmsMediaAssetController::class, 'store'])->name('api.media-assets.store');
    Route::post('/api/pages', [PageApiController::class, 'store'])->name('api.pages.store');
    Route::get('/api/pages/{page}/history', [\App\Http\Controllers\Admin\PageHistoryController::class, 'index']);
    Route::get('/api/pages/{page}/history/{revision}', [\App\Http\Controllers\Admin\PageHistoryController::class, 'show'])->whereNumber('revision');
    Route::post('/api/pages/{page}/history', [\App\Http\Controllers\Admin\PageHistoryController::class, 'checkpoint']);
    Route::post('/api/pages/{page}/history/{revision}/restore', [\App\Http\Controllers\Admin\PageHistoryController::class, 'restore'])->whereNumber('revision');
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
    Route::get('/website-design', AdminAppController::class)->name('website-design');
    Route::get('/api/website-design', [\App\Http\Controllers\Admin\WebsiteDesignController::class, 'show']);
    Route::put('/api/website-design', [\App\Http\Controllers\Admin\WebsiteDesignController::class, 'update']);
    Route::get('/website-design/cart-drawer', [\App\Http\Controllers\Admin\WebsiteDesignController::class, 'drawer'])->name('website-design.cart-drawer');
    Route::get('/website-design/logistics-homepage-sample', \App\Http\Controllers\Admin\LogisticsHomepageSampleController::class)->name('website-design.logistics-homepage-sample');
    Route::get('/website-design/preview', [\App\Http\Controllers\Admin\WebsiteDesignController::class, 'preview'])->name('website-design.preview');
    Route::get('/website-design/acceptance', [\App\Http\Controllers\Admin\WebsiteThemeAcceptanceController::class, 'index']);
    Route::get('/website-design/acceptance/sample/{scenario}', [\App\Http\Controllers\Admin\WebsiteThemeAcceptanceController::class, 'sample'])->name('website-design.acceptance.sample');
    Route::get('/website-design/acceptance/reference/{scenario}', [\App\Http\Controllers\Admin\WebsiteThemeAcceptanceController::class, 'reference']);
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
    Route::post('/api/storefront-shell-preview', \App\Http\Controllers\Admin\ShellDesignPreviewController::class)->name('shell-design.preview');
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
    Route::get('/enquiries', AdminAppController::class)->name('enquiries');
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
    Route::post('/api/inventory/store-management/allocations', [\App\Http\Controllers\Admin\StoreInventoryAllocationController::class, 'store']);
    Route::post('/api/inventory/store-management/allocations/{review}/confirm', [\App\Http\Controllers\Admin\StoreInventoryAllocationController::class, 'confirm'])->whereUuid('review');
    Route::post('/api/inventory/store-management/batch-first-counts', [\App\Http\Controllers\Admin\BatchFirstCountController::class, 'review']);
    Route::post('/api/inventory/store-management/direct-allocations', [\App\Http\Controllers\Admin\DirectStockAllocationController::class, 'store']);
    Route::post('/api/inventory/store-management/first-count-baseline/observe', [\App\Http\Controllers\Admin\MarketplaceBaselineFirstCountController::class, 'observe'])->middleware('throttle:20,1');
    Route::post('/api/inventory/store-management/first-count-baseline/commit', [\App\Http\Controllers\Admin\MarketplaceBaselineFirstCountController::class, 'commit'])->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stock-retrieval-runs', [\App\Http\Controllers\Admin\BulkMarketplaceStockRetrievalController::class, 'start'])->middleware('throttle:5,1');
    Route::get('/api/inventory/store-management/stock-retrieval-runs/latest', [\App\Http\Controllers\Admin\BulkMarketplaceStockRetrievalController::class, 'latest']);
    Route::get('/api/inventory/store-management/stock-retrieval-runs/{run}', [\App\Http\Controllers\Admin\BulkMarketplaceStockRetrievalController::class, 'show'])->whereUuid('run');
    Route::post('/api/inventory/store-management/stock-retrieval-runs/{run}/resume', [\App\Http\Controllers\Admin\BulkMarketplaceStockRetrievalController::class, 'resume'])->whereUuid('run')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/batch-first-counts/{review}/confirm', [\App\Http\Controllers\Admin\BatchFirstCountController::class, 'confirm'])->whereUuid('review');
    Route::get('/api/inventory/store-management/overview', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'overview']);
    Route::get('/api/inventory/store-management/shared-skus/{variant}/ledger', [\App\Http\Controllers\Admin\SharedPoolLedgerController::class, 'show'])->whereNumber('variant');
    Route::post('/api/inventory/store-management/shared-skus/{variant}/marketplace-stock', \App\Http\Controllers\Admin\SharedFirstCountMarketplaceReadController::class)->whereNumber('variant')->middleware('throttle:20,1');
    Route::get('/api/inventory/store-management/rebaseline/readiness', [\App\Http\Controllers\Admin\InventoryRebaselineController::class, 'readiness']);
    Route::get('/inventory/thumbnails/{variant}', \App\Http\Controllers\Admin\InventoryThumbnailController::class)
        ->whereNumber('variant')->name('admin.inventory.thumbnail');
    Route::get('/api/inventory/store-management/stores', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'stores']);
    Route::get('/api/inventory/store-management/stores/{store}/facilities', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'facilities'])->whereNumber('store');
    Route::get('/api/inventory/store-management/stores/{store}/skus/{variant}/facilities', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'facilityBalances'])->whereNumber('store')->whereNumber('variant');
    Route::get('/api/inventory/store-management/stores/{store}/skus/{variant}/test-facility-acceptance', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'testFacilityAcceptance'])->whereNumber('store')->whereNumber('variant');
    Route::get('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/stock-take-recovery', [\App\Http\Controllers\Admin\StockTakeRecoveryController::class, 'inspect'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/stock-take-recovery/historical-review', [\App\Http\Controllers\Admin\StockTakeRecoveryController::class, 'reviewHistorical'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/stock-take-recovery/order-line-review', [\App\Http\Controllers\Admin\StockTakeRecoveryController::class, 'reviewHistorical'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stores/{store}/skus/{variant}/facilities/migration-preview', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'facilityMigrationPreview'])->whereNumber('store')->whereNumber('variant')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/refresh', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'refreshFacilities'])->whereNumber('store')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/binding-reviews', [\App\Http\Controllers\Admin\FacilityBindingController::class, 'review'])->whereNumber('store')->whereNumber('facility')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/facility-binding-reviews/{review}/confirm', [\App\Http\Controllers\Admin\FacilityBindingController::class, 'confirm'])->whereUuid('review')->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/stock-reviews', [\App\Http\Controllers\Admin\FacilityStockReviewController::class, 'review'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant')->middleware('throttle:20,1');
    Route::post('/api/inventory/store-management/facility-stock-reviews/{review}/confirm', [\App\Http\Controllers\Admin\FacilityStockReviewController::class, 'confirm'])->whereUuid('review')->middleware('throttle:20,1');
    Route::post('/api/inventory/store-management/facility-transfer-reviews', [\App\Http\Controllers\Admin\FacilityTransferReviewController::class, 'review'])->middleware('throttle:20,1');
    Route::post('/api/inventory/store-management/facility-transfer-reviews/{review}/confirm', [\App\Http\Controllers\Admin\FacilityTransferReviewController::class, 'confirm'])->whereUuid('review')->middleware('throttle:20,1');
    Route::get('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/sync', [\App\Http\Controllers\Admin\FacilitySyncRecoveryController::class, 'details'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/sync/check', [\App\Http\Controllers\Admin\FacilitySyncRecoveryController::class, 'check'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant')->middleware('throttle:20,1');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/skus/{variant}/sync/resync', [\App\Http\Controllers\Admin\FacilitySyncRecoveryController::class, 'resync'])->whereNumber('store')->whereNumber('facility')->whereNumber('variant')->middleware('throttle:20,1');
    Route::get('/api/inventory/store-management/history', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'history']);
    Route::post('/api/inventory/store-management/operations/{operation}/tasks/{task}/confirmation-check', [\App\Http\Controllers\Admin\StockSyncRecoveryController::class, 'confirmation'])->whereUuid('operation')->whereUuid('task')->middleware('throttle:30,1');
    Route::get('/api/inventory/store-management/operations/{operation}', [\App\Http\Controllers\Admin\StoreInventoryReadController::class, 'operation'])->whereUuid('operation');
    Route::post('/api/inventory/store-management/operations/{operation}/check', [\App\Http\Controllers\Admin\StoreInventorySyncController::class, 'checkOperation'])->whereUuid('operation')->middleware('throttle:30,1');
    Route::post('/api/inventory/store-management/sync-all', [\App\Http\Controllers\Admin\StoreInventorySyncController::class, 'syncAll']);
    Route::get('/api/inventory/store-management/stores/{store}/skus/{variant}/sync', [\App\Http\Controllers\Admin\StockSyncRecoveryController::class, 'show'])->whereNumber(['store','variant']);
    Route::post('/api/inventory/store-management/stores/{store}/skus/{variant}/sync/check', [\App\Http\Controllers\Admin\StockSyncRecoveryController::class, 'check'])->whereNumber(['store','variant'])->middleware('throttle:30,1');
    Route::post('/api/inventory/store-management/sync-status/bulk-check', [\App\Http\Controllers\Admin\StockSyncRecoveryController::class, 'bulkCheck'])->middleware('throttle:30,1');
    Route::post('/api/inventory/store-management/stores/{store}/skus/{variant}/sync/resync', [\App\Http\Controllers\Admin\StockSyncRecoveryController::class, 'resync'])->whereNumber(['store','variant'])->middleware('throttle:10,1');
    Route::post('/api/inventory/store-management/sync-tasks/{task}/retry', [\App\Http\Controllers\Admin\StoreInventorySyncController::class, 'retry'])->whereUuid('task');
    Route::get('/api/inventory/store-management/stores/{store}/readiness', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'readiness'])->whereNumber('store');
    Route::get('/api/inventory/store-management/stores/{store}/sync-readiness', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'syncReadiness'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/pause-sync', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'pauseSync'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/resume-sync', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'resumeSync'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/takeover', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'takeover'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/pause', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'pause'])->whereNumber('store');
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/takeover', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'facilityTakeover'])->whereNumber(['store', 'facility']);
    Route::post('/api/inventory/store-management/stores/{store}/facilities/{facility}/pause', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'facilityPause'])->whereNumber(['store', 'facility']);
    Route::post('/api/inventory/store-management/mode', [\App\Http\Controllers\Admin\StoreInventorySettingsController::class, 'mode']);
    Route::get('/api/marketplace/connections', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'connections']);
    Route::get('/api/marketplace/oauth-result', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'result']);
    Route::get('/api/marketplace/oauth-batches/{batch}', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'batch'])->whereUuid('batch');
    Route::post('/api/marketplace/oauth-batches/{batch}/retry', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'retryBatch'])->whereUuid('batch')->middleware('throttle:10,1');
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
    Route::get('/marketplace/inventory-preview-43', function () {
        abort_unless(app()->environment('local'), 404);
        $path = public_path('build-inventory43-preview/manifest.json');
        abort_unless(is_file($path), 503, 'Build the isolated Stock Sync preview first.');
        $manifest = json_decode(file_get_contents($path), true);
        $entry = $manifest['src/previews/inventory43/main.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.inventory43-preview', ['entry' => $entry])->header('Cache-Control', 'no-store');
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
    Route::get('/marketplace/inventory-preview-48', function () {
        abort_unless(app()->environment('local'), 404);
        $path = public_path('build-inventory48-preview/manifest.json');
        abort_unless(is_file($path), 503, 'Build the isolated Inventory 48 preview first.');
        $manifest = json_decode(file_get_contents($path), true);
        $entry = $manifest['src/previews/inventory48/main.tsx'] ?? null;
        abort_unless($entry, 503);
        return response()->view('admin.inventory48-preview', ['entry' => $entry])->header('Cache-Control', 'no-store');
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
    Route::get('/api/marketplace/hub-events', \App\Http\Controllers\Admin\MarketplaceHubEventLedgerController::class);
    Route::middleware(\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':read')->group(function () {
    Route::get('/api/marketplace/inventory', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'index']);
    Route::get('/api/marketplace/inventory/exceptions', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'exceptions']);
    Route::get('/api/marketplace/inventory/reconciliation', [\App\Http\Controllers\Admin\MarketplaceInventoryController::class, 'reconciliation']);
    Route::get('/api/marketplace/publishing-tasks/{task}', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'show'])->whereNumber('task');
    Route::post('/api/marketplace/publishing-tasks/{task}/recovery-authorizations', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'authorizeRecovery'])->whereNumber('task')->middleware('throttle:10,1');
    Route::post('/api/marketplace/publishing-tasks/{task}/cancel-unsent', [\App\Http\Controllers\Admin\MarketplacePublishingController::class, 'cancelUnsent'])->whereNumber('task')->middleware('throttle:10,1');
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
    Route::post('/api/marketplace/orders/{order}/refresh', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'refresh'])->whereNumber('order')->middleware('throttle:10,1');
    Route::post('/api/marketplace/orders/{order}/pack', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'pack'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}/fulfillment-attempts/{attempt}', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'fulfillmentAttempt'])->whereNumber(['order','attempt']);
    Route::post('/api/marketplace/orders/{order}/fulfillment-attempts/{attempt}/reconcile', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'reconcileRts'])->whereNumber(['order','attempt'])->middleware('throttle:10,1');
    Route::post('/api/marketplace/orders/{order}/ready-to-ship', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'readyToShip'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}/label', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'label'])->whereNumber('order')->middleware('throttle:10,1');
    Route::get('/api/marketplace/orders/{order}', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'show'])->whereNumber('order');
    Route::post('/api/marketplace/stores/{store}/orders-fetch', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'fetch'])->whereNumber('store')->middleware('throttle:10,1');
    Route::get('/api/marketplace/stores/{store}/order-runs/latest', [\App\Http\Controllers\Admin\MarketplaceOrderController::class, 'latest'])->whereNumber('store');
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
        ->middleware([\App\Http\Middleware\EnsureMarketplaceOperationsEnabled::class.':publish']);
    Route::post('/api/marketplace/shopee/authorize', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'start'])->middleware('throttle:10,1');
    Route::post('/api/marketplace/lazada/authorize', [\App\Http\Controllers\Admin\LazadaOAuthController::class, 'start'])->middleware('throttle:10,1');
    Route::get('/marketplace/oauth/lazada/callback', [\App\Http\Controllers\Admin\LazadaOAuthController::class, 'callback'])->middleware('throttle:20,1');
    Route::post('/api/marketplace/stores/{store}/disconnect', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'disconnect'])->whereNumber('store');
    Route::patch('/api/marketplace/stores/{store}/capabilities/product-publishing', [\App\Http\Controllers\Admin\MarketplaceOAuthController::class, 'setProductPublishing'])->whereNumber('store')->middleware('throttle:10,1');
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
    Route::get('/support-overview', AdminAppController::class)->name('support-overview');
    Route::get('/social/facebook', AdminAppController::class)->name('social.facebook');
    Route::get('/integration', AdminAppController::class)->name('integration');
    Route::get('/integration/ai-connect', AdminAppController::class)->name('integration.ai-connect');
    Route::get('/ai-auto-reply/{section}', AdminAppController::class)->whereIn('section',['personality','knowledge','guardrails','improvement']);
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
    Route::get('/settings/search', AdminAppController::class)->name('settings.search');
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

Route::get('/webhooks/instagram/messages', [\App\Http\Controllers\InstagramWebhookController::class, 'verify']);
Route::post('/webhooks/instagram/messages', [\App\Http\Controllers\InstagramWebhookController::class, 'handle']);
Route::middleware(["auth","admin"])->prefix("admin")->group(function(){
    Route::get('/social/instagram/authorize', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'authorize'])->middleware('throttle:10,1');
    Route::get('/social/instagram/callback', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'callback'])->middleware('throttle:20,1');
    Route::get('/social/instagram/hub-return', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'hubReturn'])->middleware('throttle:20,1');
    Route::get('/api/social/instagram', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'index'])->middleware('throttle:30,1');
    Route::post('/api/social/instagram/select', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'select'])->middleware('throttle:10,1');
    Route::post('/api/social/instagram/{id}/check', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'check'])->middleware('throttle:30,1');
    Route::post('/api/social/instagram/{id}/disconnect', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'disconnect'])->middleware('throttle:10,1');
    Route::delete('/api/social/instagram/{id}', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'destroy'])->middleware('throttle:10,1');
    Route::post('/api/social/instagram/{id}/inbound', [\App\Http\Controllers\Admin\InstagramAccountController::class, 'inbound'])->middleware('throttle:10,1');
    Route::get('/social/tiktok/prototype', AdminAppController::class)->name('social.tiktok.prototype');
    Route::get('/social/tiktok', AdminAppController::class)->name('social.tiktok');
    Route::get('/social/instagram', AdminAppController::class)->name('social.instagram');
    Route::get('/social/whatsapp', AdminAppController::class)->name('social.whatsapp');
    Route::get('/social/whatsapp/authorize',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'authorize'])->middleware('throttle:10,1');
    Route::get('/social/whatsapp/hub-return',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'hubReturn'])->middleware('throttle:20,1');
    Route::post('/api/social/whatsapp/select',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'select'])->middleware('throttle:20,1');
    Route::get('/api/social/whatsapp',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'index'])->middleware('throttle:30,1');
    Route::post('/api/social/whatsapp/{id}/{action}',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'manage'])->middleware('throttle:30,1');
    Route::delete('/api/social/whatsapp/{id}',[\App\Http\Controllers\Admin\WhatsAppAccountController::class,'destroy'])->middleware('throttle:10,1');
});

Route::get('/sitemap.xml', [\App\Http\Controllers\SearchDiscoveryController::class,'sitemap']);
Route::get('/robots.txt', [\App\Http\Controllers\SearchDiscoveryController::class,'robots']);

require __DIR__.'/marketing.php';

// Optional first-party campaign attribution, independent of social OAuth.
Route::get('/m/r/{link}',[\App\Http\Controllers\MarketingTrackingController::class,'redirect'])->whereUuid('link')->middleware('throttle:120,1');
Route::post('/marketing/tracking/consent',[\App\Http\Controllers\MarketingTrackingController::class,'consent'])->middleware('throttle:30,1');
Route::post('/marketing/tracking/arrival',[\App\Http\Controllers\MarketingTrackingController::class,'arrival'])->middleware('throttle:30,1');

Route::post('/marketing/tracking/page',[\App\Http\Controllers\MarketingTrackingController::class,'page'])->middleware('throttle:120,1');
