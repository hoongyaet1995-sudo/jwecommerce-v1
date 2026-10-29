<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e($pageTitle); ?> | <?php echo e('JW Studio'); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/images/logo_icon.png')); ?>">
    <link rel="stylesheet" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/libs/bootstrap-icons/bootstrap-icons.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/css/email-editor-shell.css?v=' . '1')); ?>">
    <link rel="stylesheet" href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/email-builder/email-editor.css?v=' . '1')); ?>">
</head>
<body class="crm-email-editor-page">
    <header class="crm-email-editor-header">
        <div class="crm-email-editor-brand">
            <img src="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/images/logo_icon.png')); ?>" alt="JW Studio">
            <div>
                <strong>JW Studio Email Builder</strong>
                <span><?php echo e($pageTitle); ?></span>
            </div>
        </div>
        <div class="crm-email-editor-header-actions">
            <span class="crm-email-editor-state" data-email-editor-state aria-live="polite">Loading editor...</span>
            <button type="button" class="crm-email-editor-starters" data-starter-manager-open disabled>
                <i class="bi bi-layout-text-window-reverse" aria-hidden="true"></i>
                <span>Ready Templates</span>
            </button>
            <button type="submit" form="template-editor-form" class="crm-email-editor-save" data-email-editor-save disabled>
                <i class="bi bi-check-lg" aria-hidden="true"></i>
                <span><?php echo $isEditMode ? 'Update Template' : 'Save Template'; ?></span>
            </button>
            <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('templates')); ?>" class="crm-email-editor-close" data-email-editor-close aria-label="Close editor and return to templates">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Close</span>
            </a>
        </div>
    </header>

    <form id="template-editor-form" class="crm-email-editor-form" action="<?php echo e($formAction); ?>" method="post" data-template-editor-form>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="channel" value="email"><input type="hidden" name="version" value="{{ $version }}"><input type="hidden" name="version" value="{{ $version }}">
        <input type="hidden" name="editor_type" value="email_builder_js">
        <input type="hidden" name="editor_version" value="0.0.9">
        <textarea id="template-editor-json" name="editor_json" hidden><?php echo e($form['editor_json']); ?></textarea>
        <textarea id="template-content-html" name="content_html" hidden><?php echo e($form['content_html']); ?></textarea>

        <aside class="crm-email-editor-details" aria-labelledby="template-details-title">
            <div class="crm-email-editor-sidebar-heading">
                <span>Email Template</span>
                <h1 id="template-details-title">Template Details</h1>
                <p>Set the template information, then build the email on the canvas.</p>
            </div>

            <?php if ($selectedStarterTemplate !== null) { ?>
                <div class="crm-email-editor-starter-note">
                    <i class="bi bi-stars" aria-hidden="true"></i>
                    <div>
                        <strong>Started with <?php echo e($selectedStarterTemplate['name']); ?></strong>
                        <span>This is an editable copy and has not been saved yet.</span>
                    </div>
                </div>
            <?php } ?>

            <?php if ($formErrors !== array()) { ?>
                <div class="crm-email-editor-errors" role="alert">
                    <strong>Please check the template.</strong>
                    <ul>
                        <?php foreach ($formErrors as $formError) { ?>
                            <li><?php echo e($formError); ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

            <div class="crm-email-editor-field">
                <label for="template-name">Template Name <span aria-hidden="true">*</span></label>
                <input id="template-name" type="text" name="template_name" value="<?php echo e($form['name']); ?>" maxlength="150" placeholder="e.g. Monthly newsletter" required>
            </div>
            <div class="crm-email-editor-field">
                <label for="template-status">Status</label>
                <select id="template-status" name="status">
                    <option value="draft"<?php echo $form['status'] === 'draft' ? ' selected' : ''; ?>>Draft</option>
                    <option value="active"<?php echo $form['status'] === 'active' ? ' selected' : ''; ?>>Active</option>

                </select>
            </div>
            <div class="crm-email-editor-field">
                <label for="template-subject">Email Subject <span aria-hidden="true">*</span></label>
                <input id="template-subject" type="text" name="subject" value="<?php echo e($form['subject']); ?>" maxlength="255" placeholder="Your email subject" required>
            </div>
            <div class="crm-email-editor-field">
                <label for="template-preheader">Preheader Text</label>
                <textarea id="template-preheader" name="preheader" maxlength="255" rows="3" placeholder="Short preview shown beside the subject"><?php echo e($form['preheader']); ?></textarea>
            </div>

            <div class="crm-email-editor-variables">
                <div class="crm-email-editor-sidebar-heading crm-email-editor-sidebar-heading-small">
                    <span>Personalisation</span>
                    <h2>Available Fields</h2>
                    <p>Click a field to copy it, then paste it into a text block.</p>
                </div>
                <div class="crm-email-editor-variable-list">
                    <button type="button" data-template-variable="@{{first_name}}">First name</button>
                    <button type="button" data-template-variable="@{{last_name}}">Last name</button>
                    <button type="button" data-template-variable="@{{email}}">Email</button>
                    <button type="button" data-template-variable="@{{store_name}}">Store name</button>
                    <button type="button" data-template-variable="@{{unsubscribe_url}}">Unsubscribe link</button>
                </div>
            </div>
        </aside>

        <main class="crm-email-editor-workspace" aria-label="Email design workspace">
            <div id="xenibox-email-builder">
                <div class="crm-email-editor-loading">
                    <span class="crm-email-editor-spinner" aria-hidden="true"></span>
                    <strong>Preparing your email builder...</strong>
                </div>
            </div>
        </main>
    </form>

    <div class="crm-starter-manager" data-starter-manager hidden>
        <button type="button" class="crm-starter-manager-backdrop" data-starter-manager-close aria-label="Close ready templates"></button>
        <section class="crm-starter-manager-dialog" role="dialog" aria-modal="true" aria-labelledby="starter-manager-title">
            <header class="crm-starter-manager-header">
                <div>
                    <span>Design Library</span>
                    <h2 id="starter-manager-title">Choose a Ready Template</h2>
                    <p>Apply a suggestion as your starting point, then edit every block in the builder.</p>
                </div>
                <button type="button" class="crm-starter-manager-icon" data-starter-manager-close aria-label="Close ready templates">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <?php if ($editorStarterTemplates !== array()) { ?>
                <div class="crm-starter-manager-grid">
                    <?php foreach ($editorStarterTemplates as $starterTemplate) { ?>
                        <article class="crm-starter-card">
                            <div class="crm-starter-card-preview crm-starter-preview-<?php echo e($starterTemplate['preview_variant']); ?>" style="--starter-accent: <?php echo e($starterTemplate['accent_color']); ?>;">
                                <div class="crm-starter-preview-email">
                                    <span class="crm-starter-preview-tag"></span>
                                    <span class="crm-starter-preview-heading"></span>
                                    <span class="crm-starter-preview-line crm-starter-preview-line-wide"></span>
                                    <span class="crm-starter-preview-line"></span>
                                    <span class="crm-starter-preview-feature"></span>
                                    <span class="crm-starter-preview-button"></span>
                                    <span class="crm-starter-preview-line crm-starter-preview-line-short"></span>
                                </div>
                            </div>
                            <div class="crm-starter-card-body">
                                <span><?php echo e($starterTemplate['category']); ?></span>
                                <h3><?php echo e($starterTemplate['name']); ?></h3>
                                <p><?php echo e($starterTemplate['description']); ?></p>
                                <button type="button" data-starter-template-id="<?php echo (int) $starterTemplate['starter_template_id']; ?>">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                    <span>Use This Design</span>
                                </button>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="crm-starter-manager-empty">
                    <i class="bi bi-layout-text-window-reverse" aria-hidden="true"></i>
                    <strong>No ready templates available</strong>
                    <span>New design suggestions can be enabled from the CRM database.</span>
                </div>
            <?php } ?>

            <footer class="crm-starter-manager-footer">
                <span>Applying a design does not save a template automatically.</span>
                <button type="button" data-starter-manager-close>Continue Editing</button>
            </footer>
        </section>
    </div>

    <div class="crm-media-manager" data-media-manager hidden>
        <button type="button" class="crm-media-manager-backdrop" data-media-manager-close aria-label="Close image manager"></button>
        <section class="crm-media-manager-dialog" role="dialog" aria-modal="true" aria-labelledby="media-manager-title">
            <header class="crm-media-manager-header">
                <div>
                    <span>Email Images</span>
                    <h2 id="media-manager-title">Image Library</h2>
                    <p>Upload or select from the shared library. Large images resize to fit 1200 x 1600 pixels.</p>
                </div>
                <button type="button" class="crm-media-manager-icon" data-media-manager-close aria-label="Close image manager">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <div class="crm-media-manager-tools">
                <div class="crm-media-tool-actions">
                    <label class="crm-media-upload-button" for="media-manager-upload">
                        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                        <span>Upload Image</span>
                    </label>
                    <a href="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/image-library')); ?>" class="crm-media-library-link" target="_blank" rel="noopener"><i class="bi bi-folder2-open"></i><span>Manage Library</span></a>
                </div>
                <input id="media-manager-upload" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" data-media-manager-upload hidden>
                <form class="crm-media-search" data-media-manager-search-form>
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" maxlength="100" placeholder="Search image history" aria-label="Search image history" data-media-manager-search>
                    <button type="submit">Search</button>
                </form>
            </div>

            <div class="crm-media-manager-message" data-media-manager-message aria-live="polite"></div>
            <div class="crm-media-manager-grid" data-media-manager-grid></div>
            <div class="crm-media-manager-empty" data-media-manager-empty hidden>
                <i class="bi bi-images" aria-hidden="true"></i>
                <strong>No images found</strong>
                <span>Upload your first email image to start the library.</span>
            </div>

            <footer class="crm-media-manager-footer">
                <span data-media-manager-count>0 images</span>
                <button type="button" class="crm-media-load-more" data-media-manager-load-more hidden>Load More</button>
            </footer>
        </section>
    </div>

    <script>
        window.xeniboxEmailEditorConfig = <?php echo $editorConfiguration === false ? '{}' : $editorConfiguration; ?>;
    </script>
    <script src="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/js/email-editor-shell.js?v=' . '1')); ?>" defer></script>
    <script type="module" src="<?php echo e(\App\Services\Marketing\Email\SourceView::url('assets/email-builder/email-editor.js?v=' . '1')); ?>"></script>
</body>
</html>
