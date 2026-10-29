(function () {
    'use strict';

    var form = document.querySelector('[data-sms-template-form]');

    if (!form) {
        return;
    }

    var messageInput = form.querySelector('[data-sms-message]');
    var characterCount = form.querySelector('[data-sms-character-count]');
    var previewCount = form.querySelector('[data-sms-preview-count]');
    var creditCount = form.querySelector('[data-sms-credit-count]');
    var encodingLabel = form.querySelector('[data-sms-encoding-label]');
    var previewMessage = form.querySelector('[data-sms-preview-message]');
    var limitStatus = form.querySelector('[data-sms-limit-status]');
    var limitIcon = form.querySelector('[data-sms-limit-icon]');
    var limitCopy = form.querySelector('[data-sms-limit-copy]');
    var variableButtons = form.querySelectorAll('[data-sms-variable]');

    function analyseMessage(message) {
        var count = Array.from(message.trim()).length;
        var basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        var extension = "\f^{}\\[~]|€", units = 0;
        var isNormal = Array.from(message.trim()).every(function(c){if(basic.includes(c)){units++;return true;}if(extension.includes(c)){units+=2;return true;}return false;});
        if(!isNormal) units=message.trim().length;
        var limits = isNormal ? [160, 306, 459] : [70, 134, 201];
        var credits = 0;
        var currentLimit = limits[0];

        if (units > 0) {
            credits = limits.length + 1;

            for (var index = 0; index < limits.length; index += 1) {
                if (units <= limits[index]) {
                    credits = index + 1;
                    break;
                }
            }

            if (credits > limits.length) {
                credits = limits.length + Math.ceil(
                    (units - limits[limits.length - 1]) / (limits[2] - limits[1])
                );
            }

            currentLimit = limits[Math.min(credits - 1, limits.length - 1)];
        }

        return {
            count: count,
            credits: credits,
            encodingLabel: isNormal ? 'Standard SMS' : 'Unicode SMS',
            maximum: limits[limits.length - 1],
            remaining: Math.max(0, currentLimit - units),
            withinLimit: units <= limits[limits.length - 1]
        };
    }

    function updatePreview() {
        var message = messageInput.value.trim();
        var analysis = analyseMessage(messageInput.value);

        characterCount.textContent = analysis.count;
        previewCount.textContent = analysis.count;
        creditCount.textContent = analysis.credits;
        encodingLabel.textContent = analysis.encodingLabel;
        previewMessage.textContent = message || 'Your SMS message will appear here.';
        limitStatus.classList.toggle('is-over-limit', !analysis.withinLimit);
        limitIcon.className = 'bi ' + (analysis.withinLimit ? 'bi-info-circle' : 'bi-exclamation-triangle');

        if (analysis.count === 0) {
            limitCopy.textContent = 'Start typing to calculate SMS credits.';
        } else if (!analysis.withinLimit) {
            limitCopy.textContent = 'Message exceeds the three-credit limit.';
        } else {
            limitCopy.textContent = analysis.remaining + ' characters remain in this credit.';
        }
    }

    function insertVariable(variable) {
        var start = typeof messageInput.selectionStart === 'number'
            ? messageInput.selectionStart
            : messageInput.value.length;
        var end = typeof messageInput.selectionEnd === 'number'
            ? messageInput.selectionEnd
            : messageInput.value.length;
        var nextValue = messageInput.value.slice(0, start) + variable + messageInput.value.slice(end);

        if (messageInput.maxLength > 0 && nextValue.length > messageInput.maxLength) {
            return;
        }

        messageInput.value = nextValue;
        messageInput.focus();
        messageInput.setSelectionRange(start + variable.length, start + variable.length);
        updatePreview();
    }

    messageInput.addEventListener('input', updatePreview);

    variableButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            insertVariable(button.getAttribute('data-sms-variable') || '');
        });
    });

    updatePreview();
}());
