@props(['proof', 'variant' => 'hero'])
@php
    $status = $proof['proofStatus'] ?? 'missing';
    $compact = $variant === 'compact';
    $canUpload = (bool) ($proof['canUpload'] ?? false);
    $canReplace = (bool) ($proof['canReplace'] ?? false);
    $formatAmount = app(\App\Support\StorefrontCurrencyContext::class);
    $heading = match ($status) {
        'verified' => 'Payment Verified & Settled',
        'uploaded' => 'Payment Proof Pending Verification',
        'rejected' => 'Payment Proof Verification Required',
        default => 'Upload Payment Proof',
    };
    $badge = match ($status) {
        'verified' => 'Verified',
        'uploaded' => 'Pending Verification',
        'rejected' => 'Rejected',
        default => 'Action Required',
    };
    $uploadedLabel = ($proof['uploadedAt'] ?? null)
        ? \Illuminate\Support\Carbon::parse($proof['uploadedAt'])->format('M j, H:i')
        : null;
    $formId = 'payment-proof-form-'.($proof['paymentId'] ?? 'unknown');
@endphp

<div data-bank-payment-proof data-proof-state="{{ $status }}" class="w-full font-sans transition-colors duration-200 {{ $compact ? 'bg-zinc-50 dark:bg-zinc-950/80 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 sm:p-5' : 'bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-5 sm:p-7 shadow-sm' }}" aria-live="polite">
    @if($canUpload)
        <input data-proof-input form="{{ $formId }}" type="file" name="payment_proof" accept="image/jpeg,image/png,application/pdf" class="hidden" aria-label="Upload bank transaction slip or payment receipt">
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3.5 border-b border-zinc-200 dark:border-zinc-800/80">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 border {{ $status === 'verified' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : ($status === 'rejected' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20') }}">
                <i data-lucide="{{ $status === 'verified' ? 'circle-check' : ($status === 'rejected' ? 'circle-alert' : ($status === 'uploaded' ? 'clock' : 'upload-cloud')) }}" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 data-proof-heading class="font-bold font-serif text-zinc-900 dark:text-zinc-100 {{ $compact ? 'text-sm' : 'text-base' }}">{{ $heading }}</h3>
                    <span data-proof-badge class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider border {{ $status === 'verified' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : ($status === 'rejected' ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20' : ($status === 'uploaded' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border-zinc-200 dark:border-zinc-700')) }}">{{ $badge }}</span>
                </div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">Bank In / DuitNow Instant Transfer • Total: <strong class="text-zinc-800 dark:text-zinc-200 font-mono">{{ $formatAmount->formatSnapshot($proof['orderTotal'], $proof['currency'], $proof['currencyExchangeRate']) }} {{ $proof['currency'] }}</strong></p>
            </div>
        </div>

        <div class="flex items-center gap-1.5 self-start sm:self-auto shrink-0 bg-zinc-100 dark:bg-zinc-900 px-2.5 py-1 rounded-lg border border-zinc-200 dark:border-zinc-800">
            <span class="text-[10px] font-mono text-zinc-500">REF:</span>
            <span data-proof-reference class="text-xs font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $proof['orderNumber'] }}</span>
            <button type="button" data-proof-copy class="p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors cursor-pointer" title="Copy transfer reference" aria-label="Copy transfer reference order number"><i data-lucide="copy" class="w-3 h-3"></i></button>
        </div>
    </div>

    <div class="pt-4 space-y-3.5">
        <div data-proof-server-state class="{{ $status === 'missing' ? 'hidden' : '' }}">
            @if($status === 'uploaded')
                <div class="space-y-3">
                    <div class="p-4 bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-500/30 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start sm:items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5 sm:mt-0"><i data-lucide="circle-check" class="w-4 h-4 stroke-[2.5]"></i></div>
                            <div class="min-w-0"><div class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5 flex-wrap"><span>Payment proof submitted — awaiting verification</span></div><div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5 truncate max-w-[240px] sm:max-w-sm">{{ $proof['originalFileName'] }}@if($uploadedLabel)<span class="mx-1">•</span><span>Uploaded {{ $uploadedLabel }}</span>@endif</div></div>
                        </div>
                        @if($canReplace)
                            <button type="button" data-proof-replace class="self-start sm:self-auto px-3 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer shrink-0 shadow-2xs" aria-label="Replace submitted payment proof"><i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400"></i><span>Replace Receipt</span></button>
                        @endif
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-relaxed px-1">Our finance team verifies transfers during studio hours (within 1–4 hours). Your order status will automatically transition to warehouse packaging once confirmed.</p>
                </div>
            @elseif($status === 'rejected')
                <div class="space-y-3">
                    <div class="p-4 bg-rose-50/80 dark:bg-rose-950/30 border border-rose-500/30 rounded-2xl space-y-2.5">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="circle-alert" class="w-4 h-4 stroke-[2.5]"></i></div>
                            <div class="flex-1 min-w-0"><span class="text-xs font-bold text-rose-900 dark:text-rose-200 block">Payment proof could not be verified</span><p class="text-xs text-rose-700 dark:text-rose-300/90 mt-1 leading-relaxed bg-white/70 dark:bg-zinc-900/70 p-2.5 rounded-xl border border-rose-200 dark:border-rose-900/50"><strong class="text-rose-800 dark:text-rose-200 font-semibold block text-[11px] uppercase tracking-wide mb-0.5">Reason provided:</strong>{{ $proof['rejectionReason'] ?: 'Please upload a clear official bank confirmation or DuitNow receipt.' }}</p></div>
                        </div>
                        <div class="flex items-center justify-between pt-1 text-xs"><span class="text-[11px] font-mono text-zinc-500">Previous: {{ $proof['originalFileName'] }}</span>@if($canUpload)<button type="button" data-proof-resubmit class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"><i data-lucide="upload-cloud" class="w-3.5 h-3.5"></i><span>Upload New Proof</span></button>@endif</div>
                    </div>
                </div>
            @elseif($status === 'verified')
                <div class="p-4 bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-500/30 rounded-2xl flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0"><div class="w-9 h-9 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0"><i data-lucide="shield-check" class="w-5 h-5 stroke-[2.5]"></i></div><div class="min-w-0"><span class="text-xs font-bold text-emerald-900 dark:text-emerald-200 block">Payment Verified &amp; Matched</span><p class="text-[11px] text-zinc-600 dark:text-zinc-400 truncate mt-0.5">Verified receipt: <strong class="font-mono text-zinc-800 dark:text-zinc-200">{{ $proof['originalFileName'] }}</strong></p></div></div>
                    <span class="px-2.5 py-1 bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-[10px] font-mono font-bold uppercase rounded-lg shrink-0">Locked</span>
                </div>
            @endif
        </div>

        @if($canUpload)
            <div data-proof-editor class="space-y-3 {{ $status === 'missing' ? '' : 'hidden' }}">
                <div data-proof-missing class="space-y-3">
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">Your studio order is safely reserved, but payment cannot be verified or marked paid until your transaction slip or DuitNow receipt is submitted.</p>
                    <div data-proof-drop role="button" tabindex="0" class="p-4 sm:p-5 border-2 border-dashed rounded-2xl flex flex-col items-center justify-center gap-2 text-center cursor-pointer transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 border-zinc-300 dark:border-zinc-700/80 bg-zinc-50/50 dark:bg-zinc-950/40 hover:bg-white dark:hover:bg-zinc-900 hover:border-zinc-400 dark:hover:border-zinc-600" aria-label="Click to upload transaction receipt or drag and drop file here">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0"><i data-lucide="upload-cloud" class="w-5 h-5"></i></div>
                        <div><div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200"><span>Click to upload transaction receipt</span><span class="hidden sm:inline font-normal text-zinc-500 dark:text-zinc-400"> or drag and drop</span></div><div class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 mt-0.5">PDF, JPG, PNG • Maximum 10 MB</div></div>
                    </div>
                </div>

                <div data-proof-selected class="hidden space-y-3">
                    <div class="p-3 sm:p-3.5 bg-white dark:bg-zinc-900 border border-amber-500/40 rounded-xl flex items-center justify-between gap-3 shadow-xs">
                        <div class="flex items-center gap-2.5 min-w-0"><div class="w-9 h-9 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0"><i data-lucide="file-text" class="w-4 h-4"></i></div><div class="min-w-0"><p data-proof-file-name class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate max-w-[200px] sm:max-w-xs md:max-w-md"></p><p data-proof-file-size class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400"></p></div></div>
                        <button type="button" data-proof-remove class="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors cursor-pointer rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800" title="Remove selected file" aria-label="Remove selected file"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>
                    <div data-proof-progress class="hidden space-y-1.5 pt-1"><div class="flex items-center justify-between text-[11px] font-mono text-zinc-500 dark:text-zinc-400"><span class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-semibold"><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>Transmitting encrypted proof to secure storage...</span><span data-proof-progress-label>15%</span></div><div class="w-full bg-zinc-200 dark:bg-zinc-800 h-2 rounded-full overflow-hidden"><div data-proof-progress-bar class="bg-amber-500 h-full transition-all duration-200 ease-out" style="width: 15%"></div></div></div>
                    <div class="flex items-center justify-end gap-2.5 pt-1">
                        <button type="button" data-proof-choose-another class="px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors cursor-pointer disabled:opacity-50">Choose Another</button>
                        <button data-proof-submit form="{{ $formId }}" type="submit" class="px-4 py-2 bg-zinc-900 dark:bg-zinc-100 hover:bg-amber-500 dark:hover:bg-amber-400 text-white dark:text-zinc-950 text-xs font-bold rounded-xl transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-xs"><i data-proof-submit-icon data-lucide="upload-cloud" class="w-3.5 h-3.5"></i><span data-proof-submit-label>Submit Payment Proof</span></button>
                    </div>
                </div>

                <div data-proof-error class="hidden p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-500/30 rounded-xl flex items-start gap-2.5 text-xs text-rose-700 dark:text-rose-400 animate-fade-in" role="alert"><i data-lucide="circle-alert" class="w-4 h-4 shrink-0 mt-0.5 text-rose-600 dark:text-rose-400"></i><div class="flex-1 leading-snug"><span class="font-semibold block">Validation Issue:</span><span data-proof-error-message></span></div></div>
            </div>

            <form id="{{ $formId }}" data-proof-form action="{{ route('customer.payment-proofs.store', [$proof['orderId'], $proof['paymentId']]) }}" method="post" enctype="multipart/form-data" class="hidden">@csrf</form>
        @endif
    </div>
</div>
