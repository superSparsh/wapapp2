function buttonIcon(type) {
    if (type === 'phone') {
        return '/images/templates/call.svg';
    }
    if (type === 'flow') {
        return '/images/templates/flow.svg';
    }
    if (type === 'quick_reply') {
        return '/images/templates/quick-reply.svg';
    }

    return '/images/templates/export.svg';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function applyCampaignPreview(root, data) {
    if (!root || !data) {
        return;
    }

    const headerType = data.header_type || 'none';
    const headerImage = root.querySelector('[data-preview-header-image]');
    const headerVideo = root.querySelector('[data-preview-header-video]');
    const headerText = root.querySelector('[data-preview-header-text]');
    const body = root.querySelector('[data-preview-body]');
    const footer = root.querySelector('[data-preview-footer]');
    const buttons = root.querySelector('[data-preview-buttons]');
    const divider = root.querySelector('[data-preview-divider]');

    const showImage = headerType === 'image' && !!data.header_image;
    const showVideo = headerType === 'video' && !!(data.header_video || data.header_image);
    const showHeaderText = (headerType === 'text' || headerType === 'location') && !!(data.header_text || '').trim();

    if (headerImage) {
        headerImage.classList.toggle('hidden', !showImage);
        if (showImage && data.header_image) {
            headerImage.src = data.header_image;
        }
    }

    if (headerVideo) {
        headerVideo.classList.toggle('hidden', !showVideo);
        if (showVideo) {
            headerVideo.src = data.header_video || data.header_image || '';
        }
    }

    if (headerText) {
        headerText.classList.toggle('hidden', !showHeaderText);
        headerText.textContent = data.header_text || '';
    }

    if (body) {
        body.textContent = data.body || 'Select a template to preview the message.';
    }

    if (footer) {
        const footerValue = (data.footer || '').trim();
        footer.classList.toggle('hidden', footerValue === '');
        footer.textContent = footerValue;
    }

    const buttonItems = Array.isArray(data.buttons) ? data.buttons : [];
    if (divider) {
        divider.classList.toggle('hidden', buttonItems.length === 0);
    }

    if (buttons) {
        buttons.innerHTML = buttonItems
            .map((button) => {
                const text = escapeHtml(button?.text || 'Button');
                const icon = buttonIcon(button?.type || 'url');

                return `<div class="flex w-full items-center justify-center gap-2 py-1">
                    <img src="${icon}" alt="" class="size-4 shrink-0" width="16" height="16">
                    <span class="text-sm font-medium leading-[1.4] whitespace-nowrap text-link-green">${text}</span>
                </div>`;
            })
            .join('');
    }
}

function readVariableValues(form) {
    if (typeof form._campaignReadFirstRow === 'function') {
        return form._campaignReadFirstRow();
    }

    const values = {};

    form.querySelectorAll('[data-campaign-variable]').forEach((input) => {
        const name = input.dataset.variableName || '';
        if (!name) {
            return;
        }
        values[name] = input.value ?? '';
    });

    return values;
}

function buildPreviewUrl(baseUrl, templateId) {
    return `${baseUrl.replace(/\/$/, '')}/${encodeURIComponent(templateId)}`;
}

async function fetchTemplatePreview(baseUrl, templateId, variables = {}) {
    const url = new URL(buildPreviewUrl(baseUrl, templateId), window.location.origin);

    Object.entries(variables).forEach(([key, value]) => {
        if (value !== '') {
            url.searchParams.append(`variables[${key}]`, value);
        }
    });

    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Preview request failed (${response.status})`);
    }

    return response.json();
}

function clearPreview(root) {
    applyCampaignPreview(root, {
        body: 'Select a template to preview the message.',
        footer: '',
        header_type: 'none',
        header_text: '',
        header_image: null,
        header_video: null,
        buttons: [],
    });
}

function syncMarketingPricing(form, select) {
    const panel = form.querySelector('[data-campaign-marketing-pricing]');
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    const option = select.selectedOptions[0];
    const show = option?.dataset.showPricing === '1'
        || (option?.dataset.category || '').toUpperCase() === 'MARKETING';

    panel.classList.toggle('hidden', !show);
}

function initTemplateSelectPreview(form) {
    const select = form.querySelector('[data-campaign-template-select]') || form.querySelector('#template_id');
    const previewRoot = form.querySelector('[data-preview-body]')?.closest('[data-preview-body], .mx-auto')
        || form.querySelector('[data-preview-body]')?.parentElement;
    const bubble = form.querySelector('[data-preview-body]')?.parentElement;
    const baseUrl = form.dataset.previewUrl;

    if (!select || !bubble || !baseUrl) {
        return;
    }

    let requestId = 0;

    const load = async () => {
        const templateId = select.value;
        const current = ++requestId;

        syncMarketingPricing(form, select);

        if (!templateId) {
            clearPreview(bubble);
            return;
        }

        try {
            const data = await fetchTemplatePreview(baseUrl, templateId);
            if (current === requestId) {
                applyCampaignPreview(bubble, data);
            }
        } catch (error) {
            console.warn('Campaign template preview failed', error);
        }
    };

    select.addEventListener('change', load);

    // Native select may be wrapped by themed-select; listen on the hidden native too.
    select.addEventListener('input', load);

    if (select.value) {
        load();
    } else {
        syncMarketingPricing(form, select);
    }
}

function initVariablePreview(form) {
    const templateId = form.dataset.templateId;
    const baseUrl = form.dataset.previewUrl;
    const bubble = form.querySelector('[data-preview-body]')?.parentElement;
    const fields = form.querySelectorAll('[data-campaign-variable]');

    if (!templateId || !baseUrl || !bubble || fields.length === 0) {
        return;
    }

    let timer = null;
    let requestId = 0;

    const load = async () => {
        const current = ++requestId;
        try {
            const data = await fetchTemplatePreview(baseUrl, templateId, readVariableValues(form));
            if (current === requestId) {
                applyCampaignPreview(bubble, data);
            }
        } catch (error) {
            console.warn('Campaign variable preview failed', error);
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(load, 200);
    };

    fields.forEach((input) => {
        input.addEventListener('input', schedule);
        input.addEventListener('change', schedule);
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function initSameValueForAll(form) {
    form.querySelectorAll('[data-same-value-for-all]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const name = checkbox.dataset.variableName || '';
            if (!name) {
                return;
            }

            const firstInput = form.querySelector(
                `[data-campaign-variable][data-variable-name="${CSS.escape(name)}"][data-row-index="0"]`,
            );
            if (!(firstInput instanceof HTMLInputElement)) {
                return;
            }

            const value = checkbox.checked ? firstInput.value : '';
            form.querySelectorAll(
                `[data-campaign-variable][data-variable-name="${CSS.escape(name)}"]:not([data-row-index="0"])`,
            ).forEach((input) => {
                if (input instanceof HTMLInputElement) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        const name = checkbox.dataset.variableName || '';
        const firstInput = form.querySelector(
            `[data-campaign-variable][data-variable-name="${CSS.escape(name)}"][data-row-index="0"]`,
        );
        if (firstInput instanceof HTMLInputElement) {
            firstInput.addEventListener('input', () => {
                if (!checkbox.checked) {
                    return;
                }
                form.querySelectorAll(
                    `[data-campaign-variable][data-variable-name="${CSS.escape(name)}"]:not([data-row-index="0"])`,
                ).forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.value = firstInput.value;
                    }
                });
            });
        }
    });
}

function initVariablesImport(form) {
    const importUrl = form.dataset.variablesImportUrl;
    const importForm = document.querySelector('[data-campaign-variables-import-form]');
    if (!importUrl || !(importForm instanceof HTMLFormElement)) {
        return;
    }

    const errorEl = importForm.querySelector('[data-import-error]');
    const successEl = importForm.querySelector('[data-import-success]');
    const submitBtn = importForm.querySelector('[data-import-submit]');

    importForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (errorEl) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
        }
        if (successEl) {
            successEl.classList.add('hidden');
            successEl.textContent = '';
        }

        const body = new FormData(importForm);
        if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = true;
        }

        try {
            const response = await fetch(importUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Import failed.');
            }

            if (successEl) {
                successEl.textContent = data.message || 'Import complete.';
                successEl.classList.remove('hidden');
            }

            window.setTimeout(() => {
                window.location.reload();
            }, 600);
        } catch (error) {
            if (errorEl) {
                errorEl.textContent = error instanceof Error ? error.message : 'Import failed.';
                errorEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn instanceof HTMLButtonElement) {
                submitBtn.disabled = false;
            }
        }
    });
}

function initVariablesGrid(form) {
    if (!form.hasAttribute('data-campaign-variables-grid')) {
        return;
    }

    form._campaignReadFirstRow = () => {
        const firstRow = form.querySelector('[data-recipient-row]');
        const values = {};
        if (!firstRow) {
            return values;
        }
        firstRow.querySelectorAll('[data-campaign-variable]').forEach((input) => {
            const name = input.dataset.variableName || '';
            if (name) {
                values[name] = input.value ?? '';
            }
        });

        return values;
    };

    initSameValueForAll(form);
    initVariablesImport(form);
}

export function initCampaignWizard() {
    document.querySelectorAll('[data-campaign-wizard]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.querySelector('[data-campaign-template-select], #template_id')) {
            initTemplateSelectPreview(form);
        }

        if (form.hasAttribute('data-campaign-variables-grid')) {
            initVariablesGrid(form);
        }

        if (form.querySelector('[data-campaign-variable]')) {
            initVariablePreview(form);
        }
    });
}
