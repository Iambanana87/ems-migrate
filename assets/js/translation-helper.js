// translation-helper.js - Helper functions for integration with app.js

function translateAfterAjax() {
    if (window.translationManager) {
        window.translationManager.forceTranslate();
    }
}

function translateVueData(vueInstance, dataObject, fieldsToTranslate = []) {
    if (window.translationManager && dataObject) {
        return window.translationManager.translateServerData(dataObject, fieldsToTranslate);
    }
    return dataObject;
}

function translateTableHeaders(tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table || !window.translationManager) return;
    const headers = table.querySelectorAll('th');
    headers.forEach(th => {
        const originalText = th.getAttribute('data-original-text') || th.textContent.trim();
        if (!th.getAttribute('data-original-text')) {
            th.setAttribute('data-original-text', originalText);
        }
        const translated = window.translationManager.translate(originalText);
        if (translated !== originalText) {
            th.textContent = translated;
        }
    });
}

function translateSelectOptions(selectElement) {
    if (!selectElement || !window.translationManager) return;
    const options = selectElement.querySelectorAll('option');
    options.forEach(option => {
        const originalText = option.getAttribute('data-original-text') || option.textContent;
        if (!option.getAttribute('data-original-text')) {
            option.setAttribute('data-original-text', originalText);
        }
        const translated = window.translationManager.translate(originalText.trim());
        if (translated !== originalText.trim()) {
            option.textContent = translated;
        }
    });
}

function translateModal(modalSelector) {
    const modal = document.querySelector(modalSelector);
    if (!modal || !window.translationManager) return;
    modal.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        const translated = window.translationManager.translate(key);
        if (element.tagName === 'INPUT' && (element.type === 'button' || element.type === 'submit')) {
            element.value = translated;
        } else if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
            element.placeholder = translated;
        } else {
            element.textContent = translated;
        }
    });
}

function translateForm(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form || !window.translationManager) return;
    form.querySelectorAll('label[data-translate]').forEach(label => {
        const key = label.getAttribute('data-translate');
        label.textContent = window.translationManager.translate(key);
    });
    form.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(input => {
        const originalPlaceholder = input.getAttribute('data-original-placeholder') || input.placeholder;
        if (!input.getAttribute('data-original-placeholder')) {
            input.setAttribute('data-original-placeholder', originalPlaceholder);
        }
        input.placeholder = window.translationManager.translate(originalPlaceholder);
    });
}

function translateButtons(containerSelector = 'body') {
    const container = document.querySelector(containerSelector);
    if (!container || !window.translationManager) return;
    container.querySelectorAll('button[data-translate]').forEach(button => {
        const key = button.getAttribute('data-translate');
        button.textContent = window.translationManager.translate(key);
    });
}

function translateChartOptions(chartOptions) {
    if (!window.translationManager || !chartOptions) return chartOptions;
    if (chartOptions.plugins && chartOptions.plugins.title && chartOptions.plugins.title.text) {
        chartOptions.plugins.title.text = window.translationManager.translate(chartOptions.plugins.title.text);
    }
    if (chartOptions.scales) {
        Object.keys(chartOptions.scales).forEach(scaleKey => {
            const scale = chartOptions.scales[scaleKey];
            if (scale.title && scale.title.text) {
                scale.title.text = window.translationManager.translate(scale.title.text);
            }
        });
    }
    return chartOptions;
}

function translateTooltips(containerSelector = 'body') {
    const container = document.querySelector(containerSelector);
    if (!container || !window.translationManager) return;
    container.querySelectorAll('[title]').forEach(element => {
        const originalTitle = element.getAttribute('data-original-title') || element.title;
        if (!element.getAttribute('data-original-title')) {
            element.setAttribute('data-original-title', originalTitle);
        }
        element.title = window.translationManager.translate(originalTitle);
    });
}

function getCurrentLanguage() {
    return window.translationManager ? window.translationManager.currentLang : 'en';
}

function onLanguageChange(callback) {
    window.addEventListener('languageChanged', (e) => {
        if (typeof callback === 'function') {
            callback(e.detail.language);
        }
    });
}

window.TranslationHelpers = {
    translateAfterAjax,
    translateVueData,
    translateTableHeaders,
    translateSelectOptions,
    translateModal,
    translateForm,
    translateButtons,
    translateChartOptions,
    translateTooltips,
    getCurrentLanguage,
    onLanguageChange
};