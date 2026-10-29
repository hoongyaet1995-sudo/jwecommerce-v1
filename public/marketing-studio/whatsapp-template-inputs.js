window.JWInitWhatsAppInputs = function(initial,storeName) {
    'use strict';

    var form = document.querySelector('[data-whatsapp-template-form]');

    if (!form) {
        return;
    }

    initial = initial || {};
    var locked = form.getAttribute('data-template-locked') === '1';
    var localName = form.querySelector('[data-whatsapp-local-name]');
    var metaName = form.querySelector('[data-whatsapp-meta-name]');
    var headerType = form.querySelector('[data-whatsapp-header-type]');
    var headerField = form.querySelector('[data-whatsapp-header-field]');
    var headerText = form.querySelector('[data-whatsapp-header-text]');
    var bodyText = form.querySelector('[data-whatsapp-body-text]');
    var footerText = form.querySelector('[data-whatsapp-footer-text]');
    var headerExamples = form.querySelector('[data-whatsapp-header-examples]');
    var bodyExamples = form.querySelector('[data-whatsapp-body-examples]');
    var buttonList = form.querySelector('[data-whatsapp-button-list]');
    var buttonEmpty = form.querySelector('[data-whatsapp-button-empty]');
    var buttons = Array.isArray(initial.buttons) ? initial.buttons.slice(0, 3) : [];
    var exampleValues = {
        header: Array.isArray(initial.header_examples) ? initial.header_examples.slice() : [],
        body: Array.isArray(initial.body_examples) ? initial.body_examples.slice() : []
    };
    var metaNameTouched = metaName && metaName.value.trim() !== '';

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 150);
    }

    function extractVariables(value) {
        var matches = [];
        var expression = /\{\{\s*([0-9]+)\s*\}\}/g;
        var match;

        while ((match = expression.exec(String(value || ''))) !== null) {
            if (matches.indexOf(parseInt(match[1], 10)) === -1) {
                matches.push(parseInt(match[1], 10));
            }
        }

        return matches.sort(function (left, right) {
            return left - right;
        });
    }

    function collectExampleValues(container, type) {
        if (!container) {
            return;
        }

        container.querySelectorAll('[data-whatsapp-example-index]').forEach(function (input) {
            exampleValues[type][parseInt(input.getAttribute('data-whatsapp-example-index'), 10)] = input.value;
        });
    }

    function renderExamples(container, textInput, type) {
        if (!container || !textInput) {
            return;
        }

        collectExampleValues(container, type);
        var variables = extractVariables(textInput.value);
        container.innerHTML = '';

        variables.forEach(function (variableNumber, index) {
            var wrapper = document.createElement('div');
            var label = document.createElement('label');
            var input = document.createElement('input');

            wrapper.className = 'crm-form-field';
            label.textContent = 'Example for {{' + variableNumber + '}}';
            input.type = 'text';
            input.name = type + '_examples[]';input.id='jw-example-'+type+'-'+variableNumber;label.htmlFor=input.id;
            input.value = exampleValues[type][index] || '';
            input.placeholder = type === 'body' ? 'Example: Jason' : 'Example value';
            input.maxLength = 1024;
            input.disabled = locked;
            input.setAttribute('data-whatsapp-example-index', String(index));
            input.addEventListener('input', updatePreview);
        if(name==='button_value[]')input.addEventListener('input',function(){var row=input.closest('.crm-whatsapp-button-row');var example=row&&row.querySelector('[name="button_example[]"]');if(!example)return;var dynamic=input.value.indexOf('{{1}}')!==-1;example.type=dynamic?'text':'hidden';example.parentElement.hidden=!dynamic;example.placeholder='Example: member-offer';example.parentElement.querySelector('label').textContent=dynamic?'Dynamic URL Example':'';});
            wrapper.appendChild(label);
            wrapper.appendChild(input);
            container.appendChild(wrapper);
        });
    }

    function replaceVariables(value, container) {
        var inputs = container ? container.querySelectorAll('input') : [];

        return String(value || '').replace(/\{\{\s*([0-9]+)\s*\}\}/g, function (match, number) {
            var input = inputs[parseInt(number, 10) - 1];
            return input && input.value.trim() !== '' ? input.value.trim() : match;
        });
    }

    function renderPreviewText(element, value) {
        var parts;

        if (!element) {
            return;
        }

        parts = String(value || '').split(/(\{\{\s*[0-9]+\s*\}\})/g);
        element.textContent = '';
        parts.forEach(function (part) {
            var token;

            if (/^\{\{\s*[0-9]+\s*\}\}$/.test(part)) {
                token = document.createElement('span');
                token.className = 'crm-whatsapp-preview-variable';
                token.textContent = part;
                element.appendChild(token);
                return;
            }

            element.appendChild(document.createTextNode(part));
        });
    }

    function addVariable(target) {
        if (!target || locked) {
            return;
        }

        var variables = extractVariables(target.value);
        var nextNumber = variables.length === 0 ? 1 : Math.max.apply(null, variables) + 1;
        var token = '{{' + nextNumber + '}}';
        var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
        var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : target.value.length;
        var before = target.value.slice(0, start);
        var after = target.value.slice(end);
        var prefix = before !== '' && !/\s$/.test(before) ? ' ' : '';
        var suffix = after !== '' && !/^\s/.test(after) ? ' ' : '';

        target.value = before + prefix + token + suffix + after;
        target.focus();
        target.setSelectionRange(
            start + prefix.length + token.length,
            start + prefix.length + token.length
        );
        target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function countText(input, selector) {
        var output = form.querySelector(selector);

        if (input && output) {
            output.textContent = String(input.value.length);
        }
    }

    var fieldSerial=0;
    function buttonField(labelText, name, value, placeholder, type) {
        var wrapper = document.createElement('div');
        var label = document.createElement('label');
        var input = document.createElement('input');

        wrapper.className = 'crm-form-field';
        label.textContent = labelText;
        input.type = type || 'text';
        input.name = name;input.id="jw-button-field-"+(++fieldSerial);label.htmlFor=input.id;
        input.value = value || '';
        input.placeholder = placeholder || '';
        input.maxLength = name === 'button_text[]' ? 25 : 2000;
        input.disabled = locked;
        input.addEventListener('input', updatePreview);
        if(name==='button_value[]')input.addEventListener('input',function(){var row=input.closest('.crm-whatsapp-button-row');var example=row&&row.querySelector('[name="button_example[]"]');if(!example)return;var dynamic=input.value.indexOf('{{1}}')!==-1;example.type=dynamic?'text':'hidden';example.parentElement.hidden=!dynamic;example.placeholder='Example: member-offer';example.parentElement.querySelector('label').textContent=dynamic?'Dynamic URL Example':'';});
        wrapper.hidden = type === 'hidden';
        wrapper.appendChild(label);
        wrapper.appendChild(input);

        return wrapper;
    }

    function renderButtons() {
        if (!buttonList) {
            return;
        }

        buttonList.innerHTML = '';
        buttonEmpty.hidden = buttons.length > 0;

        buttons.forEach(function (button, index) {
            var row = document.createElement('div');
            var heading = document.createElement('div');
            var title = document.createElement('strong');
            var remove = document.createElement('button');
            var grid = document.createElement('div');
            var typeField = document.createElement('div');
            var typeLabel = document.createElement('label');
            var select = document.createElement('select');

            row.className = 'crm-whatsapp-button-row';
            heading.className = 'crm-whatsapp-button-heading';
            title.textContent = 'Button ' + (index + 1);
            remove.type = 'button';
            remove.className = 'crm-whatsapp-button-remove';
            remove.innerHTML = '<i class="bi bi-trash3"></i><span>Remove</span>';
            remove.hidden = locked;
            remove.addEventListener('click', function () {
                buttons.splice(index, 1);
                renderButtons();
                updatePreview();
            });
            heading.appendChild(title);
            heading.appendChild(remove);
            row.appendChild(heading);

            grid.className = 'crm-whatsapp-template-grid';
            typeField.className = 'crm-form-field';
            typeLabel.textContent = 'Button Type';
            select.name = 'button_type[]';
            select.className = 'form-select';
            select.disabled = locked;
            [
                ['URL', 'Visit Website'],
                ['PHONE_NUMBER', 'Call Phone Number'],
                ['QUICK_REPLY', 'Quick Reply']
            ].forEach(function (optionData) {
                var option = document.createElement('option');
                option.value = optionData[0];
                option.textContent = optionData[1];
                option.selected = button.type === optionData[0];
                select.appendChild(option);
            });
            select.addEventListener('change', function () {
                buttons[index].type = select.value;
                renderButtons();
                updatePreview();
            });
            typeField.appendChild(typeLabel);
            typeField.appendChild(select);
            grid.appendChild(typeField);
            grid.appendChild(buttonField('Button Text', 'button_text[]', button.text, 'Example: Shop Now'));

            if (button.type === 'URL') {
                grid.appendChild(buttonField(
                    'Website URL',
                    'button_value[]',
                    button.value,
                    'https://store.example.com/offer/{{1}}',
                    'text'
                ));

                if (String(button.value || '').indexOf('{{1}}') !== -1) {
                    grid.appendChild(buttonField(
                        'Dynamic URL Example',
                        'button_example[]',
                        button.example,
                        'Example: member-offer'
                    ));
                } else {
                    grid.appendChild(buttonField('', 'button_example[]', '', '', 'hidden'));
                }
            } else if (button.type === 'PHONE_NUMBER') {
                grid.appendChild(buttonField(
                    'Phone Number',
                    'button_value[]',
                    button.value,
                    '+60123456789',
                    'tel'
                ));
                grid.appendChild(buttonField('', 'button_example[]', '', '', 'hidden'));
            } else {
                grid.appendChild(buttonField('', 'button_value[]', '', '', 'hidden'));
                grid.appendChild(buttonField('', 'button_example[]', '', '', 'hidden'));
            }

            row.appendChild(grid);
            buttonList.appendChild(row);
        });
    }

    function syncButtonsFromInputs() {
        if (!buttonList) {
            return;
        }

        buttonList.querySelectorAll('.crm-whatsapp-button-row').forEach(function (row, index) {
            if (!buttons[index]) {
                return;
            }

            var typeInput = row.querySelector('[name="button_type[]"]');
            var textInput = row.querySelector('[name="button_text[]"]');
            var valueInput = row.querySelector('[name="button_value[]"]');
            var exampleInput = row.querySelector('[name="button_example[]"]');
            buttons[index].type = typeInput ? typeInput.value : buttons[index].type;
            buttons[index].text = textInput ? textInput.value : '';
            buttons[index].value = valueInput ? valueInput.value : '';
            buttons[index].example = exampleInput ? exampleInput.value : '';
        });
    }

    function updatePreview() {
        syncButtonsFromInputs();
        var previewName = form.querySelector('[data-whatsapp-preview-name]');
        var previewHeader = form.querySelector('[data-whatsapp-preview-header]');
        var previewBody = form.querySelector('[data-whatsapp-preview-body]');
        var previewFooter = form.querySelector('[data-whatsapp-preview-footer]');
        var previewButtons = form.querySelector('[data-whatsapp-preview-buttons]');
        var headerValue = headerType && headerType.value === 'TEXT' && headerText
            ? replaceVariables(headerText.value, headerExamples)
            : '';
        var bodyValue = bodyText ? replaceVariables(bodyText.value, bodyExamples) : '';
        var footerValue = footerText ? footerText.value.trim() : '';

        previewName.textContent = storeName || 'Your store';
        previewHeader.hidden = headerValue === '';
        renderPreviewText(previewHeader, headerValue);
        renderPreviewText(
            previewBody,
            bodyValue === '' ? 'Your WhatsApp message will appear here.' : bodyValue
        );
        previewFooter.hidden = footerValue === '';
        previewFooter.textContent = footerValue;
        previewButtons.innerHTML = '';
        buttons.forEach(function (button) {
            if (!button.text) {
                return;
            }

            var item = document.createElement('span');
            var icon = button.type === 'PHONE_NUMBER'
                ? 'bi-telephone'
                : (button.type === 'URL' ? 'bi-box-arrow-up-right' : 'bi-reply');
            item.innerHTML = '<i class="bi ' + icon + '"></i>';
            item.appendChild(document.createTextNode(button.text));
            previewButtons.appendChild(item);
        });

        countText(headerText, '[data-whatsapp-header-count]');
        countText(bodyText, '[data-whatsapp-body-count]');
        countText(footerText, '[data-whatsapp-footer-count]');
    }

    if (localName && metaName) {
        localName.addEventListener('input', function () {
            if (!metaNameTouched) {
                metaName.value = slugify(localName.value);
            }
            updatePreview();
        });
        metaName.addEventListener('input', function () {
            metaNameTouched = metaName.value.trim() !== '';
            metaName.value = slugify(metaName.value);
        });
    }

    if (headerType) {
        headerType.addEventListener('change', function () {
            headerField.hidden = headerType.value !== 'TEXT';
            updatePreview();
        });
    }

    [headerText, bodyText, footerText].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            if (input === headerText) {
                renderExamples(headerExamples, headerText, 'header');
            } else if (input === bodyText) {
                renderExamples(bodyExamples, bodyText, 'body');
            }
            updatePreview();
        });
    });

    form.querySelectorAll('[data-whatsapp-add-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
            addVariable(button.getAttribute('data-whatsapp-add-variable') === 'header' ? headerText : bodyText);
        });
    });

    var addButton = form.querySelector('[data-whatsapp-add-button]');

    if (addButton) {
        addButton.addEventListener('click', function () {
            syncButtonsFromInputs();

            if (buttons.length >= 3) {
                return;
            }

            buttons.push({
                type: 'URL',
                text: '',
                value: '',
                example: ''
            });
            renderButtons();
            updatePreview();
        });
    }

    renderExamples(headerExamples, headerText, 'header');
    renderExamples(bodyExamples, bodyText, 'body');
    renderButtons();
    updatePreview();
    return function(){collectExampleValues(headerExamples,'header');collectExampleValues(bodyExamples,'body');syncButtonsFromInputs();return {header_examples:exampleValues.header.slice(),body_examples:exampleValues.body.slice(),buttons:buttons.slice()};};
};
