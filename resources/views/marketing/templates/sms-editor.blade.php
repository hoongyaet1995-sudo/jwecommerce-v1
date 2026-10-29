<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>SMS Template · JW Marketing Studio</title><link rel="stylesheet" href="{{ asset('marketing-studio/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('marketing-studio/bootstrap-icons.css') }}"><link rel="stylesheet" href="{{ asset('marketing-studio/main.css') }}"><link rel="stylesheet" href="{{ asset('marketing-studio/xenibox.css') }}"><link rel="stylesheet" href="{{ asset('marketing-studio/studio.css') }}"><link rel="stylesheet" href="{{ asset('marketing-studio/navigation-preview.css') }}?v={{ filemtime(public_path('marketing-studio/navigation-preview.css')) }}"><link rel="stylesheet" href="{{ asset('marketing-studio/navigation.css') }}?v={{ filemtime(public_path('marketing-studio/navigation.css')) }}"><link rel="stylesheet" href="{{ asset('marketing-studio/controls.css') }}?v={{ filemtime(public_path('marketing-studio/controls.css')) }}"><script defer src="{{ asset('marketing-studio/campaign-navigation.js') }}?v={{ filemtime(public_path('marketing-studio/campaign-navigation.js')) }}"></script><link rel="stylesheet" href="{{ asset('marketing-studio/crm-email/css/sms-template.css') }}"></head><body>
<aside class="sidebar-wrapper" id="sidebar"><a class="sidebar-brand xenibox-sidebar-brand" href="{{ route('marketing.home') }}"><span class="studio-brand">JW <small>MARKETING STUDIO</small></span></a><div class="flex-grow-1 overflow-y-auto"><div class="sidebar-menu-section"><div class="sidebar-menu-title">MARKETING WORKSPACE</div><nav aria-label="Marketing Studio"><ul class="sidebar-menu-list" id="navigation">@include('marketing.navigation.items')</ul></nav></div></div><div class="sidebar-profile"><div class="sidebar-profile-avatar">J</div><div class="sidebar-profile-info"><div class="sidebar-profile-name">{{ auth()->user()->name }}</div><div class="sidebar-profile-email">Store owner</div></div></div></aside>
<div class="main-wrapper"><header class="navbar-custom"><div class="navbar-left"><button class="btn-desktop-toggle me-3" id="menu" aria-label="Toggle navigation"><i class="bi bi-list"></i></button><div class="crm-client-badge"><span class="crm-client-badge-name">{{ app(\App\Support\ShopBrand::class)->name() }}</span></div></div><div class="navbar-actions"><span class="sample-indicator">Template library</span><span class="navbar-profile-avatar">J</span><span class="navbar-profile-name d-none d-md-inline">{{ auth()->user()->name }}</span></div></header>
@if(($navigationBoot['page']??'')==='overview'&&!config('marketing.execution_enabled'))<div class="alert alert-warning mx-4 mt-4" role="status">Production is unavailable on this shared host. You can browse and prepare content. Rendering will open after VPS migration and runtime verification. No generation charges will occur.</div>@endif<div class="page-header"><div><div class="eyebrow">MARKETING STUDIO / TEMPLATES</div><h1 class="page-title">SMS Template</h1><p class="page-subtitle">Prepare reusable messages for your campaigns.</p></div></div><main class="crm-page-content"><div class="crm-page-toolbar crm-create-segment-toolbar">

    <div>

        <div class="crm-toolbar-label">SMS Template</div>

        <div class="crm-toolbar-value">Create a reusable message for future SMS campaigns.</div>

    </div>

    <div class="crm-toolbar-actions">

        <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates')); ?>" class="btn-crm-link">

            <i class="bi bi-arrow-left"></i>

            <span>Back to Templates</span>

        </a>

    </div>

</div>



<?php if ($formErrors !== array()) { ?>

    <div class="alert alert-danger crm-form-alert" role="alert">

        <strong>Please check the SMS template.</strong>

        <ul>

            <?php foreach (array_unique($formErrors) as $formError) { ?>

                <li><?php echo e($formError); ?></li>

            <?php } ?>

        </ul>

    </div>

<?php } ?>



<form action="<?php echo e($formAction); ?>" method="post" data-sms-template-form>

    <?php echo csrf_field(); ?>

    <input type="hidden" name="channel" value="sms"><input type="hidden" name="version" value="{{ $version }}">



    <div class="crm-sms-template-layout">

        <div>

            <section class="crm-segment-section" aria-labelledby="sms-template-details-title">

                <div class="crm-section-heading">

                    <h2 id="sms-template-details-title">Template Details</h2>

                    <p>Name the template and choose when it is available for an SMS campaign.</p>

                </div>

                <div class="crm-sms-template-grid">

                    <div class="crm-form-field crm-form-field-full">

                        <label for="sms-template-name">Template Name <span aria-hidden="true">*</span></label>

                        <input id="sms-template-name" type="text" name="template_name" value="<?php echo e($form['name']); ?>" maxlength="150" placeholder="Example: Weekend Member Offer">

                    </div>

                    <div class="crm-form-field crm-form-field-full">

                        <label for="sms-template-status">Status <span aria-hidden="true">*</span></label>

                        <select id="sms-template-status" name="status" class="form-select">

                            <option value="draft"<?php echo $form['status'] === 'draft' ? ' selected' : ''; ?>>Draft</option>

                            <option value="active"<?php echo $form['status'] === 'active' ? ' selected' : ''; ?>>Active</option>

                            <?php if ($form['status'] === 'archived') { ?>

                                <option value="archived" selected>Archived</option>

                            <?php } ?>

                        </select>

                        <div class="crm-form-help">Only active templates will be available when creating an SMS campaign.</div>

                    </div>

                </div>

            </section>



            <section class="crm-segment-section" aria-labelledby="sms-template-content-title">

                <div class="crm-section-heading">

                    <h2 id="sms-template-content-title">Message Content</h2>

                    <p>Keep the message clear and monitor the estimated credit usage while typing.</p>

                </div>

                <div class="crm-form-field crm-form-field-full">

                    <div class="crm-sms-field-heading">

                        <label for="sms-template-message">SMS Message <span aria-hidden="true">*</span></label>

                        <span><strong data-sms-character-count><?php echo (int) $analysis['character_count']; ?></strong> characters</span>

                    </div>

                    <textarea id="sms-template-message" name="sms_message" rows="9" maxlength="1000" placeholder="Hi @{{first_name}}, enjoy 10% off your next order this weekend." data-sms-message><?php echo e($form['content_text']); ?></textarea>

                </div>



                <div class="crm-sms-variables" aria-label="Personalisation variables">

                    <span>Personalisation</span>

                    <div>

                        <button type="button" data-sms-variable="@{{first_name}}">First name</button>

                        <button type="button" data-sms-variable="@{{last_name}}">Last name</button>

                        <button type="button" data-sms-variable="@{{store_name}}">Store name</button>

                    </div>

                    <small>Variable values may be longer than their labels, so final recipient credit usage can vary.</small>

                </div>



                <div class="crm-sms-credit-guide">

                    <div><strong>Standard SMS</strong><span>1 credit: 160 &middot; 2 credits: 306 &middot; 3 credits: 459 characters</span></div>

                    <div><strong>Unicode SMS</strong><span>1 credit: 70 &middot; 2 credits: 134 &middot; 3 credits: 201 characters</span></div>

                    <p>Joined messages reserve a few characters for delivery information, so two standard credits hold 306 characters instead of 320.</p>

                </div>

            </section>

        </div>



        <aside class="crm-sms-template-preview-card">

            <div class="crm-preview-heading">

                <div class="crm-preview-icon"><i class="bi bi-chat-square-text"></i></div>

                <div>

                    <div class="crm-toolbar-label">SMS Preview</div>

                    <div class="crm-inbox-preview-title">How the message may appear</div>

                </div>

            </div>



            <div class="crm-sms-phone-preview">

                <div class="crm-sms-phone-topbar"><span>9:41</span><div><i class="bi bi-reception-4"></i><i class="bi bi-wifi"></i><i class="bi bi-battery-full"></i></div></div>

                <div class="crm-sms-contact">

                    <span><i class="bi bi-chevron-left"></i></span>

                    <div class="crm-sms-contact-icon"><i class="bi bi-shop"></i></div>

                    <strong><?php echo e($crmClientBrand); ?></strong>

                    <span><i class="bi bi-info-circle"></i></span>

                </div>

                <div class="crm-sms-conversation">

                    <time>Today 9:41 AM</time>

                    <div class="crm-sms-message-bubble" data-sms-preview-message><?php echo e($form['content_text'] === '' ? 'Your SMS message will appear here.' : $form['content_text']); ?></div>

                </div>

            </div>



            <div class="crm-sms-usage-grid">

                <div><span>Characters</span><strong data-sms-preview-count><?php echo (int) $analysis['character_count']; ?></strong></div>

                <div><span>Estimated Credits</span><strong data-sms-credit-count><?php echo (int) $analysis['estimated_credits']; ?></strong></div>

                <div><span>Message Type</span><strong data-sms-encoding-label><?php echo e($analysis['encoding_label']); ?></strong></div>

            </div>



            <div class="crm-sms-limit-status<?php echo $analysis['within_limit'] ? '' : ' is-over-limit'; ?>" data-sms-limit-status>

                <i class="bi <?php echo $analysis['within_limit'] ? 'bi-info-circle' : 'bi-exclamation-triangle'; ?>" data-sms-limit-icon></i>

                <span data-sms-limit-copy>

                    <?php if ((int) $analysis['character_count'] === 0) { ?>Start typing to calculate SMS credits.<?php } else { ?><?php echo number_format((int) $analysis['remaining_characters']); ?> characters remain in this credit.<?php } ?>

                </span>

            </div>



            <div class="crm-sms-template-actions">

                <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates')); ?>" class="btn-crm-secondary">Cancel</a>

                <button type="submit" class="btn-crm-primary">

                    <i class="bi bi-check-lg"></i>

                    <span><?php echo $isEditMode ? 'Update Template' : 'Save Template'; ?></span>

                </button>

            </div>

        </aside>

    </div>

</form>

</main><footer class="footer-custom">Saving a template does not send messages.</footer></div><script src="{{ asset('marketing-studio/crm-email/js/sms-template.js') }}"></script><script src="{{ asset('marketing-studio/crm-email/js/sms-save.js') }}"></script></body></html>