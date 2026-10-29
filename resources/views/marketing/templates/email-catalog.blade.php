<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Ready Email Templates | <?php echo e('JW Studio'); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/images/logo_icon.png')); ?>">
    <link rel="stylesheet" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/libs/bootstrap-icons/bootstrap-icons.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/css/template-catalog.css?v=' . '1')); ?>">
</head>
<body class="crm-template-catalog-page">
    <header class="crm-template-catalog-header">
        <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates')); ?>" class="crm-template-catalog-brand" aria-label="Return to templates">
            <img src="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/images/logo_icon.png')); ?>" alt="JW Studio">
            <div>
                <strong>JW Studio Template Library</strong>
                <span>Ready email designs</span>
            </div>
        </a>
        <div class="crm-template-catalog-header-actions">
            <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates/create/email/editor')); ?>" class="crm-template-catalog-new">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span>Create New Template</span>
            </a>
            <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates')); ?>" class="crm-template-catalog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Close</span>
            </a>
        </div>
    </header>

    <main class="crm-template-catalog-layout">
        <aside class="crm-template-catalog-sidebar" aria-labelledby="catalog-category-title">
            <div class="crm-template-catalog-sidebar-heading">
                <span>Browse</span>
                <h1 id="catalog-category-title">Categories</h1>
                <p>Choose a purpose to narrow the ready designs.</p>
            </div>
            <nav class="crm-template-category-list" aria-label="Ready template categories">
                <button type="button" class="is-active" data-template-category="all" aria-pressed="true">
                    <span>All Templates</span>
                    <strong><?php echo count($catalogTemplates); ?></strong>
                </button>
                <?php foreach ($categoryCounts as $categoryName => $categoryCount) { ?>
                    <button type="button" data-template-category="<?php echo e(strtolower($categoryName)); ?>" aria-pressed="false">
                        <span><?php echo e($categoryName); ?></span>
                        <strong><?php echo (int) $categoryCount; ?></strong>
                    </button>
                <?php } ?>
            </nav>
            <div class="crm-template-catalog-tip">
                <i class="bi bi-lightbulb" aria-hidden="true"></i>
                <div>
                    <strong>Everything stays editable</strong>
                    <span>Change text, images, colours and sections after opening a design.</span>
                </div>
            </div>
        </aside>

        <section class="crm-template-catalog-content" aria-labelledby="catalog-title">
            <div class="crm-template-catalog-intro">
                <div>
                    <span>Start with inspiration</span>
                    <h2 id="catalog-title">Ready Email Templates</h2>
                    <p>Select a design to customise, or start with a completely blank canvas.</p>
                </div>
                <div class="crm-template-catalog-count" data-template-catalog-count>
                    <?php echo count($catalogTemplates); ?> designs
                </div>
            </div>

            <?php if ($catalogTemplates !== array()) { ?>
                <div class="crm-template-catalog-grid" data-template-catalog-grid>
                    <?php foreach ($catalogTemplates as $catalogIndex => $starterTemplate) { ?>
                        <article
                            class="crm-template-catalog-card"
                            data-template-card
                            data-template-category-name="<?php echo e(strtolower($starterTemplate['category'])); ?>"
                            data-template-name="<?php echo e($starterTemplate['name']); ?>"
                            <?php echo $catalogIndex >= 6 ? ' hidden' : ''; ?>
                        >
                            <div class="crm-template-catalog-card-preview">
                                <iframe
                                    title="<?php echo e($starterTemplate['name']); ?> preview"
                                    srcdoc="<?php echo e($starterTemplate['preview_html']); ?>"
                                    loading="lazy"
                                    sandbox=""
                                    tabindex="-1"
                                ></iframe>
                            </div>
                            <div class="crm-template-catalog-card-body">
                                <span><?php echo e($starterTemplate['category']); ?></span>
                                <h3><?php echo e($starterTemplate['name']); ?></h3>
                                <p><?php echo e($starterTemplate['description']); ?></p>
                                <div class="crm-template-catalog-card-actions">
                                    <button type="button" data-template-preview>
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                        <span>Preview</span>
                                    </button>
                                    <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates/create/email/editor/starter/' . (int) $starterTemplate['starter_template_id'])); ?>">
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                        <span>Use Template</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php } ?>
                </div>
                <div class="crm-template-catalog-loader" data-template-catalog-loader>
                    <button type="button" data-template-load-more>
                        <i class="bi bi-arrow-down-circle" aria-hidden="true"></i>
                        <span>Load More Templates</span>
                    </button>
                    <span data-template-catalog-end hidden>You have reached the end of the catalog.</span>
                </div>
            <?php } else { ?>
                <div class="crm-template-catalog-empty">
                    <i class="bi bi-layout-text-window-reverse" aria-hidden="true"></i>
                    <strong>No ready templates available</strong>
                    <span>Start with a blank design while the suggestion library is being prepared.</span>
                    <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates/create/email/editor')); ?>">Create New Template</a>
                </div>
            <?php } ?>
        </section>
    </main>

    <div class="crm-template-preview-modal" data-template-preview-modal hidden>
        <button type="button" class="crm-template-preview-backdrop" data-template-preview-close aria-label="Close preview"></button>
        <section class="crm-template-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="template-preview-title">
            <header>
                <div>
                    <span>Template Preview</span>
                    <h2 id="template-preview-title" data-template-preview-title>Email Template</h2>
                </div>
                <button type="button" data-template-preview-close aria-label="Close preview">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>
            <div class="crm-template-preview-frame">
                <iframe title="Full email template preview" data-template-preview-frame sandbox=""></iframe>
            </div>
        </section>
    </div>

    <script>
        window.xeniboxTemplateCatalogConfig = <?php echo $catalogConfiguration === false ? '{}' : $catalogConfiguration; ?>;
    </script>
    <script src="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/js/template-catalog.js?v=' . '1')); ?>" defer></script>
</body>
</html>
