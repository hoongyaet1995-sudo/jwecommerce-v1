(function () {
    'use strict';

    var form = document.querySelector('[data-template-editor-form]');

    if (!form) {
        return;
    }

    var saveButton = document.querySelector('[data-email-editor-save]');
    var closeButton = document.querySelector('[data-email-editor-close]');
    var stateLabel = document.querySelector('[data-email-editor-state]');
    var jsonField = document.getElementById('template-editor-json');
    var initialJson = jsonField ? jsonField.value : '';
    var initialFields = new FormData(form);
    var editorReady = false;
    var isDirty = false;
    var isSubmitting = false;
    var mediaManager = document.querySelector('[data-media-manager]');
    var mediaGrid = document.querySelector('[data-media-manager-grid]');
    var mediaEmpty = document.querySelector('[data-media-manager-empty]');
    var mediaMessage = document.querySelector('[data-media-manager-message]');
    var mediaCount = document.querySelector('[data-media-manager-count]');
    var mediaLoadMore = document.querySelector('[data-media-manager-load-more]');
    var mediaUpload = document.querySelector('[data-media-manager-upload]');
    var mediaSearch = document.querySelector('[data-media-manager-search]');
    var mediaSearchForm = document.querySelector('[data-media-manager-search-form]');
    var mediaConfiguration = window.xeniboxEmailEditorConfig || {};
    var starterManager = document.querySelector('[data-starter-manager]');
    var starterManagerOpenButton = document.querySelector('[data-starter-manager-open]');
    var starterTemplates = mediaConfiguration.starterTemplates || [];
    var mediaPage = 1;
    var mediaTotalPages = 1;
    var mediaTotalRows = 0;
    var mediaLoading = false;

    function setState(message, status) {
        stateLabel.textContent = message;
        stateLabel.setAttribute('data-status', status || 'neutral');
    }

    function markDirty() {
        if (!isSubmitting) {
            isDirty = true;
            setState('Unsaved changes', 'warning');
        }
    }

    function setMediaMessage(message, status) {
        mediaMessage.textContent = message || '';
        mediaMessage.setAttribute('data-status', status || 'neutral');
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }

        if (bytes < 1048576) {
            return Math.round(bytes / 1024) + ' KB';
        }

        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function selectMediaItem(item) {
        window.dispatchEvent(new CustomEvent('xenibox:media-selected', {
            detail: {
                url: item.url,
                altText: item.alt_text || item.name.replace(/\.[^.]+$/, '')
            }
        }));
        mediaManager.hidden = true;
        document.body.classList.remove('crm-media-manager-open');
        setState('Image selected', 'ready');
    }

    function createMediaCard(item) {
        var button = document.createElement('button');
        var preview = document.createElement('span');
        var image = document.createElement('img');
        var details = document.createElement('span');
        var name = document.createElement('strong');
        var meta = document.createElement('small');

        button.type = 'button';
        button.className = 'crm-media-card';
        button.setAttribute('aria-label', 'Use ' + item.name);
        preview.className = 'crm-media-card-preview';
        image.src = item.url;
        image.alt = item.alt_text || '';
        image.loading = 'lazy';
        details.className = 'crm-media-card-details';
        name.textContent = item.name;
        meta.textContent = item.width + ' × ' + item.height + ' · ' + formatFileSize(item.file_size);
        preview.appendChild(image);
        details.appendChild(name);
        details.appendChild(meta);
        button.appendChild(preview);
        button.appendChild(details);
        button.addEventListener('click', function () {
            selectMediaItem(item);
        });

        return button;
    }

    function renderMediaItems(items, append) {
        if (!append) {
            mediaGrid.innerHTML = '';
        }

        items.forEach(function (item) {
            mediaGrid.appendChild(createMediaCard(item));
        });

        mediaEmpty.hidden = mediaGrid.children.length > 0;
        mediaCount.textContent = mediaTotalRows + (mediaTotalRows === 1 ? ' image' : ' images');
        mediaLoadMore.hidden = mediaPage >= mediaTotalPages;
    }

    function parseMediaResponse(response) {
        return response.json().catch(function () {
            return {success: false, errors: ['The image manager returned an invalid response.']};
        }).then(function (payload) {
            if (!response.ok || !payload.success) {
                throw new Error(payload.errors && payload.errors.length ? payload.errors[0] : 'The image manager request failed.');
            }

            return payload;
        });
    }

    function loadMediaItems(page, append) {
        if (mediaLoading || !mediaConfiguration.mediaEndpoint) {
            return;
        }

        mediaLoading = true;
        mediaManager.setAttribute('aria-busy', 'true');
        setMediaMessage('Loading images...', 'loading');
        var query = '?page=' + encodeURIComponent(page) + '&search=' + encodeURIComponent(mediaSearch.value.trim());

        fetch(mediaConfiguration.mediaEndpoint + query, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        })
            .then(parseMediaResponse)
            .then(function (payload) {
                mediaPage = payload.pagination.page;
                mediaTotalPages = payload.pagination.total_pages;
                mediaTotalRows = payload.pagination.total_rows;
                renderMediaItems(payload.items, append);
                setMediaMessage('', 'neutral');
            })
            .catch(function (error) {
                setMediaMessage(error.message, 'error');
            })
            .then(function () {
                mediaLoading = false;
                mediaManager.removeAttribute('aria-busy');
            });
    }

    function openMediaManager() {
        closeStarterManager();
        mediaManager.hidden = false;
        document.body.classList.add('crm-media-manager-open');
        mediaSearch.focus();
        loadMediaItems(1, false);
    }

    function closeMediaManager() {
        mediaManager.hidden = true;
        document.body.classList.remove('crm-media-manager-open');
    }

    function openStarterManager() {
        closeMediaManager();
        starterManager.hidden = false;
        document.body.classList.add('crm-starter-manager-open');

        var firstTemplateButton = starterManager.querySelector('[data-starter-template-id]');

        if (firstTemplateButton) {
            firstTemplateButton.focus();
        }
    }

    function closeStarterManager() {
        starterManager.hidden = true;
        document.body.classList.remove('crm-starter-manager-open');
    }

    function findStarterTemplate(starterTemplateId) {
        var selectedTemplate = null;

        starterTemplates.some(function (starterTemplate) {
            if (String(starterTemplate.id) === String(starterTemplateId)) {
                selectedTemplate = starterTemplate;
                return true;
            }

            return false;
        });

        return selectedTemplate;
    }

    function applyStarterTemplate(starterTemplateId) {
        var starterTemplate = findStarterTemplate(starterTemplateId);

        if (!starterTemplate || !editorReady) {
            setState('Ready template unavailable', 'error');
            return;
        }

        if (!window.confirm('Replace the current design, subject and preheader with "' + starterTemplate.name + '"?')) {
            return;
        }

        var subjectField = document.getElementById('template-subject');
        var preheaderField = document.getElementById('template-preheader');

        if (subjectField) {
            subjectField.value = starterTemplate.subject || '';
        }

        if (preheaderField) {
            preheaderField.value = starterTemplate.preheader || '';
        }

        markDirty();
        setState('Applying ' + starterTemplate.name + '...', 'saving');
        window.dispatchEvent(new CustomEvent('xenibox:apply-starter-template', {
            detail: {document: starterTemplate.document}
        }));
        closeStarterManager();
    }

    function uploadMediaImage(file) {
        if (!file || mediaLoading) {
            return;
        }

        var formData = new FormData();
        formData.append('_token', mediaConfiguration.csrfToken || '');
        formData.append('image', file);
        mediaLoading = true;
        mediaManager.setAttribute('aria-busy', 'true');
        setMediaMessage('Uploading ' + file.name + '...', 'loading');

        fetch(mediaConfiguration.mediaEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
            body: formData
        })
            .then(parseMediaResponse)
            .then(function (payload) {
                mediaSearch.value = '';
                mediaGrid.insertBefore(createMediaCard(payload.item), mediaGrid.firstChild);
                mediaTotalRows += 1;
                mediaEmpty.hidden = true;
                mediaCount.textContent = mediaTotalRows + (mediaTotalRows === 1 ? ' image' : ' images');
                setMediaMessage('Image uploaded. Select it to use in your email.', 'success');
            })
            .catch(function (error) {
                setMediaMessage(error.message, 'error');
            })
            .then(function () {
                mediaLoading = false;
                mediaUpload.value = '';
                mediaManager.removeAttribute('aria-busy');
            });
    }

    window.addEventListener('xenibox:email-editor-ready', function () {
        editorReady = true;
        initialJson = jsonField ? jsonField.value : '';
        saveButton.disabled = false;
        starterManagerOpenButton.disabled = false;
        setState('Ready', 'ready');
    });

    window.addEventListener('xenibox:email-editor-synced', function () {
        if (editorReady && jsonField && jsonField.value !== initialJson) {
            markDirty();
        }
    });

    window.addEventListener('xenibox:email-editor-error', function () {
        saveButton.disabled = true;
        setState('Editor error', 'error');
    });

    window.addEventListener('xenibox:starter-template-applied', function () {
        markDirty();
        setState('Ready template applied', 'ready');
    });

    window.addEventListener('xenibox:starter-template-error', function () {
        setState('The ready template could not be applied', 'error');
    });

    form.addEventListener('input', function (event) {
        if (event.target === jsonField) {
            return;
        }

        var fieldName = event.target.name;

        if (!fieldName || initialFields.get(fieldName) !== event.target.value) {
            markDirty();
        }
    });

    form.addEventListener('submit', function (event) {
        if (!editorReady) {
            event.preventDefault();
            setState('Please wait for the editor', 'warning');
            return;
        }

        event.preventDefault();
        window.dispatchEvent(new CustomEvent('xenibox:email-editor-request-sync'));
        isSubmitting = true;
        saveButton.disabled = true;
        setState('Saving...', 'saving');
        fetch(form.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new FormData(form)})
          .then(function(response){return response.json().then(function(payload){if(!response.ok)throw new Error(Object.values(payload.errors||{}).flat().join(' ')||payload.message||'Could not save. Your edits are retained.');return payload;});})
          .then(function(payload){form.action='/admin/marketing/campaigns/templates/email/save/'+payload.template.id;form.querySelector('[name="version"]').value=payload.template.version;initialJson=jsonField.value;isDirty=false;history.replaceState(null,'','/admin/marketing/campaigns/templates/email/editor/'+payload.template.id);setState('Saved · Nothing sent','ready');})
          .catch(function(error){setState(error.message,'error');})
          .finally(function(){isSubmitting=false;saveButton.disabled=false;});
    });

    closeButton.addEventListener('click', function (event) {
        if (isDirty && !window.confirm('Leave the editor without saving your changes?')) {
            event.preventDefault();
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (!isDirty || isSubmitting) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    Array.prototype.forEach.call(form.querySelectorAll('[data-template-variable]'), function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-template-variable');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value);
            } else {
                var helper = document.createElement('textarea');
                helper.value = value;
                helper.setAttribute('readonly', 'readonly');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                document.execCommand('copy');
                document.body.removeChild(helper);
            }

            setState(value + ' copied', 'ready');
        });
    });

    window.addEventListener('xenibox:open-media-manager', openMediaManager);

    starterManagerOpenButton.addEventListener('click', openStarterManager);

    Array.prototype.forEach.call(document.querySelectorAll('[data-starter-manager-close]'), function (button) {
        button.addEventListener('click', closeStarterManager);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-starter-template-id]'), function (button) {
        button.addEventListener('click', function () {
            applyStarterTemplate(button.getAttribute('data-starter-template-id'));
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-media-manager-close]'), function (button) {
        button.addEventListener('click', closeMediaManager);
    });

    mediaSearchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadMediaItems(1, false);
    });

    mediaLoadMore.addEventListener('click', function () {
        if (mediaPage < mediaTotalPages) {
            loadMediaItems(mediaPage + 1, true);
        }
    });

    mediaUpload.addEventListener('change', function () {
        uploadMediaImage(mediaUpload.files && mediaUpload.files[0]);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (!starterManager.hidden) {
            closeStarterManager();
        } else if (!mediaManager.hidden) {
            closeMediaManager();
        }
    });
}());
