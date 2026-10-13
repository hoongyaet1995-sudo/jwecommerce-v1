@php
    $accountCurrencies = collect(app(\App\Support\CurrencySettings::class)->all());
    $formatAccountMoney = function ($amount, $snapshot) use ($accountCurrencies) {
        $code = $snapshot['currency'] ?? '';
        $currency = $accountCurrencies->firstWhere('code', $code)
            ?? ['symbolLeft' => $code.' ', 'symbolRight' => '', 'decimalPlaces' => 2];
        $currency['exchangeRate'] = $snapshot['currencyExchangeRate'] ?? 1;
        return app(\App\Support\StorefrontCurrencyContext::class)->formatWith($amount, $currency);
    };
    $user = $accountPayload['user'];
    $orders = $accountPayload['orders'];
    $addresses = $accountPayload['addresses'];
    $dashboard = $accountPayload['dashboard'];
    $voucherGroups = $accountPayload['vouchers'];
    $returnPolicy = $accountPayload['returnPolicy'];
    $vouchers = array_merge($voucherGroups['eligible'], $voucherGroups['used'], $voucherGroups['expired']);
    $latestOrder = $orders[0] ?? null;
    $latestAddress = collect($latestOrder['addresses'] ?? [])->firstWhere('type', 'shipping') ?? collect($latestOrder['addresses'] ?? [])->first();
    $latestPayment = collect($latestOrder['payments'] ?? [])->sortByDesc('id')->first();
    $latestPaymentStatus = str($latestPayment['gatewayStatus'] ?? '')->lower()->replace(' ', '_')->toString();
    $latestPaymentStatusLabel = filled($latestPaymentStatus) ? str($latestPaymentStatus)->replace('_', ' ')->upper()->toString() : '—';
    $latestPaymentMethodLabel = $latestPayment ? str($latestPayment['paymentMethod'])->replace('_', ' ')->upper()->toString() : '—';
    $latestPaymentIsSettled = in_array($latestPaymentStatus, ['paid', 'captured', 'succeeded', 'settled', 'completed'], true);
    $latestPaymentLastFour = filled($latestPayment['lastFour'] ?? null) ? ' (•••• '.$latestPayment['lastFour'].')' : '';
    $latestPaymentSummary = ! $latestPayment
        ? 'Payment: —'
        : ($latestPaymentIsSettled
            ? 'Paid via '.$latestPaymentMethodLabel.$latestPaymentLastFour
            : 'Payment: '.$latestPaymentStatusLabel.' via '.$latestPaymentMethodLabel.$latestPaymentLastFour);
    $hasConcreteTracking = $latestOrder
        && filled($latestOrder['trackingCarrier'] ?? null)
        && filled($latestOrder['trackingNumber'] ?? null)
        && filled($latestOrder['shippedAt'] ?? null);
    $latestLogisticsLabel = $hasConcreteTracking && ($latestOrder['status'] ?? null) === 'shipped'
        ? 'LATEST DISPATCH IN TRANSIT'
        : 'LATEST ORDER';
    $latestOrderStatusLabel = ($latestOrder['status'] ?? null) === 'shipped' && $hasConcreteTracking
        ? 'IN TRANSIT'
        : str($latestOrder['status'] ?? '')->replace('_', ' ')->upper()->toString();
    $latestDeliveryEstimate = null;
    $initial = str($user['name'] ?: 'U')->substr(0, 1)->upper();
    $memberSince = ! empty($user['memberSince']) ? \Illuminate\Support\Carbon::parse($user['memberSince'])->format('Y') : '2024';
    $accountRefreshUrl = request()->url();
    $couponApplyUrl = route('customer.coupons.apply');
    $couponStatus = session('coupon_status');
    $reviewStatuses = collect($orders)->flatMap(fn (array $order) => collect($order['items'])->mapWithKeys(function (array $item): array {
        $review = collect($item['reviews'])->sortByDesc('attemptNumber')->first();
        $status = in_array($review['status'] ?? null, ['pending', 'published'], true)
            ? ($review['status'] === 'pending' ? 'pending_approval' : 'published')
            : 'not_reviewed';

        return [(string) $item['id'] => $status];
    }))->all();
    $navSections = [
        ['group' => '1. Personal Information', 'groupCn' => '个人信息', 'items' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
            ['id' => 'profile', 'label' => 'Profile Setting', 'icon' => 'user'],
            ['id' => 'security', 'label' => 'Change Password', 'icon' => 'lock'],
            ['id' => 'addresses', 'label' => 'My Address', 'icon' => 'map-pin'],
        ]],
        ['group' => '2. Purchase', 'groupCn' => '购买与订单', 'items' => [
            ['id' => 'latest_purchase', 'label' => 'Latest Purchase', 'icon' => 'truck', 'badge' => 'Live'],
            ['id' => 'purchase_history', 'label' => 'Purchase History', 'icon' => 'history', 'badge' => count($orders) ?: null],
        ]],
        ['group' => '3. My Coupons', 'groupCn' => '我的优惠券', 'items' => [
            ['id' => 'vouchers', 'label' => 'Available Coupons', 'icon' => 'ticket', 'badge' => $voucherGroups['counts']['eligible'].' Active', 'badgeColor' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30'],
        ]],
    ];
    $sessions = [];
    $timelineEvents = [];
    if ($latestOrder) {
        $timelineEvents[] = [
            'title' => 'Order Placed',
            'description' => $latestPayment ? str($latestPayment['paymentMethod'])->replace('_', ' ')->upper().' payment status: '.str($latestPayment['gatewayStatus'])->replace('_', ' ')->upper().'.' : '—',
            'timestamp' => $latestOrder['createdAt'] ? \Illuminate\Support\Carbon::parse($latestOrder['createdAt'])->format('M j, h:i A') : '—',
            'completed' => true,
            'current' => empty($latestOrder['shippedAt']) && ! in_array($latestOrder['status'], ['delivered', 'cancelled', 'refunded'], true),
        ];
        if ($hasConcreteTracking) {
            $timelineEvents[] = [
                'title' => 'Handed Over to '.$latestOrder['trackingCarrier'],
                'description' => $latestOrder['trackingNumber'],
                'timestamp' => \Illuminate\Support\Carbon::parse($latestOrder['shippedAt'])->format('M j, h:i A'),
                'completed' => $latestOrder['status'] === 'delivered',
                'current' => $latestOrder['status'] === 'shipped',
            ];
        }
    }
    $sourceIcon = static function (string $name, string $class): string {
        $nodes = [
            'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
            'ticket' => '<path d="M2 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 0 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3Z"/><circle cx="12" cy="13" r="3"/>',
            'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
            'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
            'edit-2' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        ];

        if (! isset($nodes[$name])) {
            return sprintf('<i data-lucide="%s" class="%s"></i>', e($name), e($class));
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-%s %s" aria-hidden="true">%s</svg>', e($name), e($class), $nodes[$name]);
    };
@endphp
@php($preview=(bool)($websiteDesignPreview ?? false))
<x-layouts.app :title="'My account | '.$shopBrandName"><div
    data-tech-account
    class="te-theme"
    @hashchange.window="activeTab=['dashboard','profile','addresses','latest_purchase','purchase_history','vouchers','wishlist'].includes(location.hash.slice(1))?location.hash.slice(1):'dashboard'"
    x-data="{
        activeTab: ['dashboard','profile','addresses','latest_purchase','purchase_history','vouchers','wishlist'].includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : 'dashboard',
        profileFirstName: @js($user['firstName'] ?: str($user['name'])->before(' ')->toString()),
        profileLastName: @js($user['lastName'] ?: str($user['name'])->after(' ')->toString()),
        profilePhone: @js($user['phone'] ?? ''),
        profileSaved: false,
        couponInput: '',
        addressToast: null,
        accountRefreshUrl: @js($accountRefreshUrl),
        couponApplyUrl: @js($couponApplyUrl),
        csrfToken: @js(csrf_token()),
        showAddressModal: false,
        showInvoiceModal: false,
        statusFilter: 'all',
        searchQuery: '',
        expandedOrderId: @js($latestOrder['id'] ?? null),
        voucherSubTab: 'eligible',
        copiedVoucher: null,
        appliedToast: null,
        twoFactorEnabled: true,
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
        showCurrentPassword: false,
        showNewPassword: false,
        showConfirmPassword: false,
        passError: null,
        passSuccess: false,
        sessions: JSON.parse(atob(@js(base64_encode(json_encode($sessions))))),
        sessionToast: null,
        reviewTarget: null,
        reviewRating: 0,
        reviewHoverRating: 0,
        reviewTitle: '',
        reviewText: '',
        reviewPhotos: [],
        reviewErrors: {},
        reviewSubmitting: false,
        reviewSubmitted: false,
        returnTarget: null,
        reviewStatuses: @js($reviewStatuses),
        async submitExistingForm(form, kind) {
            this.profileSaved = false;
            this.addressToast = null;
            let response;
            try {
                response = await fetch(form.action, {
                    method: form.method,
                    body: new FormData(form),
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    credentials: 'same-origin'
                });
            } catch (error) {
                return;
            }

            if (response.redirected) {
                            window.location.assign(this.accountRefreshUrl + (kind === 'address' ? '#addresses' : '#profile'));
                return;
            }

            const contentType = response.headers.get('content-type') || '';
            if (! response.ok || ! contentType.includes('application/json')) return;
            const result = await response.json().catch(() => null);
            if (result?.ok !== true) return;

            if (kind === 'profile') {
                this.profileSaved = true;
                setTimeout(() => this.profileSaved = false, 3000);
            }
            if (kind === 'address') {
                window.location.assign(this.accountRefreshUrl + '#addresses');
            }
        },
        passwordStrength() {
            if (!this.newPassword) return {score: 0, label: 'None', color: 'bg-zinc-300 dark:bg-zinc-700'};
            let score = 0;
            if (this.newPassword.length >= 8) score++;
            if (/[A-Z]/.test(this.newPassword)) score++;
            if (/[0-9]/.test(this.newPassword)) score++;
            if (/[^A-Za-z0-9]/.test(this.newPassword)) score++;
            if (score <= 1) return {score: 25, label: 'Weak', color: 'bg-rose-500'};
            if (score === 2) return {score: 50, label: 'Fair', color: 'bg-amber-500'};
            if (score === 3) return {score: 75, label: 'Good', color: 'bg-sky-500'};
            return {score: 100, label: 'Strong (High Security)', color: 'bg-emerald-500'};
        },
        submitPassword() {
            this.passError = null;
            if (!this.currentPassword) this.passError = 'Please enter your current password.';
            else if (this.newPassword.length < 8) this.passError = 'New password must be at least 8 characters long.';
            else if (this.newPassword !== this.confirmPassword) this.passError = 'New passwords do not match. Please verify.';
            if (this.passError) return;
            this.passSuccess = true;
            this.currentPassword = this.newPassword = this.confirmPassword = '';
            setTimeout(() => this.passSuccess = false, 4000);
        },
        revokeSession(id) {
            this.sessions = this.sessions.filter(session => session.id !== id);
            this.sessionToast = 'Session revoked successfully.';
            setTimeout(() => this.sessionToast = null, 3000);
        },
        revokeOtherSessions() {
            this.sessions = this.sessions.filter(session => session.isCurrent);
            this.sessionToast = 'All other active sessions have been terminated.';
            setTimeout(() => this.sessionToast = null, 3000);
        },
        async applyVoucher(code) {
            if(window.__JW_THEME_DEMO__){this.appliedToast='Demo only. No store changes were made.';return;}
            const response = await fetch(this.couponApplyUrl, { method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken}, credentials: 'same-origin', body: JSON.stringify({code}) });
            if (response.redirected) { window.location.assign(response.url); return; }
            this.appliedToast = 'Coupon application could not be completed.';
        },
        openReview(order, item) {
            if (['pending_approval', 'pending', 'published'].includes(this.reviewStatuses[item.id])) return;
            this.reviewTarget = {order, item};
            this.reviewRating = this.reviewHoverRating = 0;
            this.reviewTitle = this.reviewText = '';
            this.reviewPhotos = [];
            this.reviewErrors = {};
            this.reviewSubmitted = false;
        },
        closeReview() {
            if (!this.reviewSubmitting) this.reviewTarget = null;
        },
        selectReviewPhotos(event) {
            this.reviewErrors.photos = null;
            for (const file of Array.from(event.target.files || [])) {
                if (this.reviewPhotos.length >= 5) { this.reviewErrors.photos = 'Up to 5 images can be uploaded.'; break; }
                if (!['image/jpeg','image/png','image/webp'].includes(file.type)) { this.reviewErrors.photos = 'Only JPEG, PNG, or WebP images are allowed.'; continue; }
                if (file.size > 5 * 1024 * 1024) { this.reviewErrors.photos = 'Each image must be 5 MB or smaller.'; continue; }
                this.reviewPhotos.push({file, url: URL.createObjectURL(file)});
            }
            event.target.value = '';
        },
        removeReviewPhoto(index) {
            URL.revokeObjectURL(this.reviewPhotos[index].url);
            this.reviewPhotos.splice(index, 1);
        },
        async submitReview(form) {
            if(window.__JW_THEME_DEMO__){this.reviewErrors.photos='Demo only. No review was submitted.';return;}
            this.reviewErrors = {};
            if (!this.reviewRating) this.reviewErrors.rating = 'Please select a star rating.';
            if (!this.reviewTitle.trim()) this.reviewErrors.title = 'Please enter a review title.';
            if (!this.reviewText.trim()) this.reviewErrors.comment = 'Please write your review.';
            if (Object.keys(this.reviewErrors).length) return;
            const body = new FormData();
            body.append('orderItemId', this.reviewTarget.item.id);
            body.append('rating', this.reviewRating);
            body.append('title', this.reviewTitle);
            body.append('comment', this.reviewText);
            this.reviewPhotos.forEach(photo => body.append('images[]', photo.file));
            this.reviewSubmitting = true;
            let response;
            try {
                response = await fetch(form.dataset.reviewEndpoint, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                    credentials: 'same-origin',
                    body
                });
            } catch (error) {
                this.reviewSubmitting = false;
                this.reviewErrors.photos = 'Your review could not be submitted.';
                return;
            }
            this.reviewSubmitting = false;
            const contentType = response.headers.get('content-type') || '';
            if (response.redirected || ! response.ok || ! contentType.includes('application/json')) {
                this.reviewErrors.photos = 'Your review could not be submitted.';
                return;
            }
            const result = await response.json().catch(() => null);
            if (! ['pending', 'pending_approval'].includes(result?.data?.status)) {
                this.reviewErrors.photos = result?.message || 'Your review could not be submitted.';
                return;
            }
            const targetId = this.reviewTarget.item.id;
            this.reviewStatuses = {...this.reviewStatuses, [targetId]: 'pending_approval'};
            this.reviewSubmitted = true;
            setTimeout(() => this.closeReview(), 1600);
        }
    }"
>

<x-storefront.nav active="account"/><main class="te-account-main te-shell">@php($accountHero=app(\App\Services\WebsiteThemeCustomization::class)->media('tech','account',asset('images/themes/tech/hero.png')))
<header class="te-account-heading">@if(!$accountHero['hidden'])<div class="te-account-hero-media" data-account-page-media>@include('storefront.partials.theme-media',['media'=>$accountHero])</div>@endif<div class="te-account-title"><p class="te-eyebrow">MY ACCOUNT</p><h1>My account</h1><p>Welcome back, {{ $user['firstName'] ?? $user['name'] }}.</p></div></header><div class="te-account-layout">
<nav class="te-account-nav" aria-label="Account navigation">@foreach(['dashboard'=>'Overview','purchase_history'=>'Orders','addresses'=>'Addresses','wishlist'=>'Saved items','profile'=>'Profile','vouchers'=>'Vouchers'] as $key=>$label)<button type="button" @click="activeTab='{{ $key }}';location.hash='{{ $key }}'" :aria-current="activeTab==='{{ $key }}' ? 'page' : null"><i data-lucide="{{ ['dashboard'=>'house','purchase_history'=>'package','addresses'=>'map-pin','wishlist'=>'heart','profile'=>'user','vouchers'=>'ticket-percent'][$key] }}"></i>{{ $label }}</button>@endforeach <form method="POST" action="{{ route('customer.logout') }}">@csrf<button @disabled($preview)><i data-lucide="log-out"></i>Sign out</button></form></nav>
<div class="te-account-content">@if(session('status'))<p role="status" class="te-notice">{{ session('status') }}</p>@endif @if($errors->any())<p role="alert" class="te-error">{{ $errors->first() }}</p>@endif
@include('storefront.themes.tech-account-overview')
<section x-show="activeTab==='profile'" x-cloak><h2>Your profile</h2><form class="te-profile-editor" method="POST" action="{{ route('customer.profile.update') }}">@csrf @method('PATCH')<h3>Personal information</h3><fieldset @disabled($preview)><label>Full name<input name="name" value="{{ old('name',$user['name']) }}" required autocomplete="name"></label><label>Phone<input name="phone" x-model="profilePhone" type="tel" autocomplete="tel"></label><label>Email address<input value="{{ $user['email'] }}" readonly autocomplete="email"></label><small>Email address cannot be changed here.</small><button class="te-primary">Save changes</button></fieldset></form></section>
@include('storefront.themes.tech-account-addresses')
@include('storefront.themes.tech-account-saved')
@include('storefront.themes.tech-account-orders')
@include('storefront.themes.tech-account-vouchers')
</div></div></main>@include('storefront.themes.tech-account-dialogs')<x-storefront.footer/></div></x-layouts.app>