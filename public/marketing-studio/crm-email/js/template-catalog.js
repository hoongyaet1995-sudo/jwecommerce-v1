(function () {
    'use strict';

    var grid = document.querySelector('[data-template-catalog-grid]');

    if (!grid) {
        return;
    }

    var configuration = window.xeniboxTemplateCatalogConfig || {};
    var batchSize = Math.max(3, parseInt(configuration.batchSize, 10) || 6);
    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-template-card]'));
    var categoryButtons = document.querySelectorAll('[data-template-category]');
    var loadMoreButton = document.querySelector('[data-template-load-more]');
    var loader = document.querySelector('[data-template-catalog-loader]');
    var endMessage = document.querySelector('[data-template-catalog-end]');
    var countLabel = document.querySelector('[data-template-catalog-count]');
    var previewModal = document.querySelector('[data-template-preview-modal]');
    var previewFrame = document.querySelector('[data-template-preview-frame]');
    var previewTitle = document.querySelector('[data-template-preview-title]');
    var activeCategory = 'all';
    var visibleLimit = batchSize;
    var observer = null;

    function matchingCards() {
        return cards.filter(function (card) {
            return activeCategory === 'all' || card.getAttribute('data-template-category-name') === activeCategory;
        });
    }

    function updateCatalog() {
        var matchedCards = matchingCards();

        cards.forEach(function (card) {
            card.hidden = true;
        });

        matchedCards.slice(0, visibleLimit).forEach(function (card) {
            card.hidden = false;
        });

        countLabel.textContent = matchedCards.length + (matchedCards.length === 1 ? ' design' : ' designs');
        loadMoreButton.hidden = visibleLimit >= matchedCards.length;
        endMessage.hidden = matchedCards.length === 0 || visibleLimit < matchedCards.length;
        loader.hidden = matchedCards.length === 0;
    }

    function loadMore() {
        visibleLimit += batchSize;
        updateCatalog();
    }

    function selectCategory(button) {
        activeCategory = button.getAttribute('data-template-category') || 'all';
        visibleLimit = batchSize;

        Array.prototype.forEach.call(categoryButtons, function (categoryButton) {
            var isActive = categoryButton === button;
            categoryButton.classList.toggle('is-active', isActive);
            categoryButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        updateCatalog();
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function openPreview(card) {
        var sourceFrame = card.querySelector('.crm-template-catalog-card-preview iframe');

        previewTitle.textContent = card.getAttribute('data-template-name') || 'Email Template';
        previewFrame.srcdoc = sourceFrame.getAttribute('srcdoc') || '';
        previewModal.hidden = false;
        document.body.classList.add('crm-template-preview-open');
    }

    function closePreview() {
        previewModal.hidden = true;
        previewFrame.srcdoc = '';
        document.body.classList.remove('crm-template-preview-open');
    }

    Array.prototype.forEach.call(categoryButtons, function (button) {
        button.addEventListener('click', function () {
            selectCategory(button);
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-template-preview]'), function (button) {
        button.addEventListener('click', function () {
            openPreview(button.closest('[data-template-card]'));
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-template-preview-close]'), function (button) {
        button.addEventListener('click', closePreview);
    });

    loadMoreButton.addEventListener('click', loadMore);

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && !loadMoreButton.hidden) {
                loadMore();
            }
        }, {rootMargin: '180px'});
        observer.observe(loader);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !previewModal.hidden) {
            closePreview();
        }
    });

    updateCatalog();
}());
