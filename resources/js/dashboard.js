function statusChipClass(variant) {
    const map = {
        new: 'bg-stat-blue/15 text-stat-blue',
        scheduled: 'bg-stat-orange/15 text-stat-orange',
        done: 'bg-stat-emerald/15 text-stat-emerald',
        approved: 'bg-stat-emerald/15 text-stat-emerald',
        'fd-approved': 'bg-green-50 text-green-700',
        'fd-error': 'bg-danger/10 text-danger',
        pending: 'bg-stat-orange/15 text-stat-orange',
        rejected: 'bg-danger/10 text-danger',
        active: 'bg-green-50 text-green-700',
        running: 'bg-teal-50 text-teal-700',
        sending: 'bg-teal-50 text-teal-700',
        paused: 'bg-divider text-text-muted',
        cancelled: 'bg-danger/10 text-danger',
        sent: 'bg-divider text-text-muted',
        inactive: 'bg-blue-50 text-primary-2',
    };

    return map[variant] || 'bg-muted-surface text-text-body';
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-IN');
}

function updateUrlParam(key, value) {
    const url = new URL(window.location.href);
    if (value === null || value === undefined || value === '') {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, String(value));
    }
    window.history.replaceState({}, '', url);
}

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const contentType = response.headers.get('content-type') || '';
    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }
    if (!contentType.includes('application/json')) {
        throw new Error('Expected JSON response');
    }

    return response.json();
}

function refreshIcon(btn) {
    return btn.querySelector('[data-refresh-icon], svg, img');
}

function refreshLabel(btn) {
    return btn.querySelector('[data-refresh-text]');
}

function ensureRefreshLabel(btn) {
    let label = refreshLabel(btn);
    if (label) {
        return label;
    }

    const icon = refreshIcon(btn);
    const textNode = [...btn.childNodes].find(
        (node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '',
    );

    label = document.createElement('span');
    label.setAttribute('data-refresh-text', '');
    label.textContent = textNode ? textNode.textContent.trim() : 'Refresh';

    if (textNode) {
        btn.replaceChild(label, textNode);
    } else if (icon?.nextSibling) {
        btn.insertBefore(label, icon.nextSibling);
    } else {
        btn.appendChild(label);
    }

    return label;
}

function clearRefreshStatusTimer(btn) {
    const timerId = Number(btn.dataset.refreshStatusTimer || 0);
    if (timerId) {
        window.clearTimeout(timerId);
        delete btn.dataset.refreshStatusTimer;
    }
}

/**
 * @param {HTMLButtonElement[]} buttons
 * @param {'idle'|'busy'|'done'|'error'} state
 */
function setRefreshState(buttons, state) {
    buttons.forEach((btn) => {
        if (!(btn instanceof HTMLButtonElement)) {
            return;
        }

        clearRefreshStatusTimer(btn);

        if (!btn.dataset.refreshIdleLabel) {
            btn.dataset.refreshIdleLabel = ensureRefreshLabel(btn).textContent.trim() || 'Refresh';
        }

        const label = ensureRefreshLabel(btn);
        const icon = refreshIcon(btn);
        const busy = state === 'busy';
        const done = state === 'done';
        const failed = state === 'error';

        btn.disabled = busy;
        btn.classList.toggle('opacity-70', busy);
        btn.classList.toggle('pointer-events-none', busy);
        btn.classList.toggle('border-green-500', done);
        btn.classList.toggle('text-green-600', done);
        btn.classList.toggle('border-danger', failed);
        btn.classList.toggle('text-danger', failed);
        btn.setAttribute('aria-busy', busy ? 'true' : 'false');

        if (icon) {
            icon.classList.toggle('animate-spin', busy);
        }

        if (busy) {
            label.textContent = 'Refreshing…';
        } else if (done) {
            label.textContent = 'Updated';
            btn.dataset.refreshStatusTimer = String(
                window.setTimeout(() => setRefreshState([btn], 'idle'), 1600),
            );
        } else if (failed) {
            label.textContent = 'Failed';
            btn.dataset.refreshStatusTimer = String(
                window.setTimeout(() => setRefreshState([btn], 'idle'), 2000),
            );
        } else {
            label.textContent = btn.dataset.refreshIdleLabel || 'Refresh';
        }
    });
}

function flashRefreshTarget(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    root.classList.remove('dashboard-refresh-flash');
    // Force reflow so the animation can replay on rapid clicks.
    void root.offsetWidth;
    root.classList.add('dashboard-refresh-flash');
}

function initCreditsFilter(root) {
    const select = root.querySelector('[data-credits-period]');
    const url = root.dataset.creditsUrl;
    if (!url) {
        return;
    }

    let requestId = 0;
    const refreshButtons = [...root.querySelectorAll('[data-credits-refresh]')];

    const load = async ({ fromRefresh = false } = {}) => {
        const period = (select instanceof HTMLSelectElement ? select.value : null) || 'daily';
        const current = ++requestId;
        updateUrlParam('credits_period', period);
        if (fromRefresh) {
            setRefreshState(refreshButtons, 'busy');
        }

        try {
            const data = await fetchJson(`${url}?period=${encodeURIComponent(period)}`);
            if (current !== requestId) {
                return;
            }

            const credits = data.credits || {};
            root.querySelectorAll('[data-credit-key]').forEach((card) => {
                const key = card.dataset.creditKey;
                const valueEl = card.querySelector('[data-credit-value]');
                if (!key || !valueEl) {
                    return;
                }
                const used = credits[key] ?? 0;
                const limit = credits[`${key}_limit`] ?? credits.sent_limit ?? 1000;
                valueEl.textContent = `${formatNumber(used)}/${formatNumber(limit)}`;
            });

            if (fromRefresh) {
                flashRefreshTarget(root.querySelector('.grid') || root);
                setRefreshState(refreshButtons, 'done');
            }
        } catch (error) {
            console.warn('Credits refresh failed', error);
            if (fromRefresh && current === requestId) {
                setRefreshState(refreshButtons, 'error');
            }
        } finally {
            if (!fromRefresh && current === requestId) {
                setRefreshState(refreshButtons, 'idle');
            }
        }
    };

    if (select instanceof HTMLSelectElement) {
        select.addEventListener('change', () => load());
    }
    refreshButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            load({ fromRefresh: true });
        });
    });
}

function renderRecipientRows(recipients) {
    if (!Array.isArray(recipients) || recipients.length === 0) {
        return `<tr class="border-t border-divider bg-elevated">
            <td colspan="7" class="fd-table-cell p-4 text-sm text-text-muted">No recipient logs for this campaign yet.</td>
        </tr>`;
    }

    return recipients
        .map((row, index) => {
            const si = String(index + 1).padStart(2, '0');
            const chip = statusChipClass(row.status_variant);
            const reason = row.reason || '—';

            return `<tr class="border-t border-divider bg-elevated">
                <td class="fd-table-cell p-2 pl-4">${si}</td>
                <td class="fd-table-name p-2">${escapeHtml(row.name || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.phone || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.campaign || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.sent_at || '—')}</td>
                <td class="p-2 text-center">
                    <span class="fd-status-chip inline-flex items-center rounded px-2 py-1 ${chip}">${escapeHtml(row.status_label || 'Pending')}</span>
                </td>
                <td class="fd-table-cell max-w-[240px] break-words p-2 text-xs" title="${escapeHtml(reason)}">${escapeHtml(reason)}</td>
            </tr>`;
        })
        .join('');
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function applyCampaignMetrics(root, campaign) {
    if (!campaign) {
        return;
    }

    const total = campaign.total_recipients || 0;
    Object.entries(campaign.metrics || {}).forEach(([key, metric]) => {
        const card = root.querySelector(`[data-metric="${key}"]`);
        if (!card) {
            return;
        }
        const countEl = card.querySelector('[data-metric-count]');
        const totalEl = card.querySelector('[data-metric-total]');
        const percentEl = card.querySelector('[data-metric-percent]');
        const barEl = card.querySelector('[data-metric-bar]');
        const detailsEl = card.querySelector('[data-metric-details]');

        if (countEl) {
            countEl.textContent = String(metric.count ?? 0);
        }
        if (totalEl) {
            totalEl.textContent = String(total);
        }
        if (percentEl) {
            percentEl.textContent = `${metric.percent ?? 0}%`;
        }
        if (barEl) {
            barEl.style.width = `${metric.percent ?? 0}%`;
        }
        if (detailsEl && campaign.details_base) {
            detailsEl.href = key === 'total'
                ? campaign.details_base
                : `${campaign.details_base}?status=${encodeURIComponent(key)}`;
        }
    });
}

function initCampaignReview(root) {
    const select = root.querySelector('[data-campaign-review-select]');
    const url = root.dataset.campaignReviewUrl;
    const rows = root.querySelector('[data-campaign-review-rows]');
    const heading = root.querySelector('[data-campaign-review-heading]');
    const moreLink = root.querySelector('[data-campaign-review-more]');

    if (!url || !rows) {
        return;
    }

    let requestId = 0;
    const refreshButtons = [...root.querySelectorAll('[data-campaign-review-refresh]')];

    const load = async ({ fromRefresh = false } = {}) => {
        const campaignId = select instanceof HTMLSelectElement ? select.value : '';
        if (!campaignId) {
            rows.innerHTML = renderRecipientRows([]);
            if (heading) {
                heading.textContent = 'Send to 0 recipients';
            }
            if (fromRefresh) {
                setRefreshState(refreshButtons, 'idle');
            }
            return;
        }

        const current = ++requestId;
        updateUrlParam('campaign_id', campaignId);
        if (fromRefresh) {
            setRefreshState(refreshButtons, 'busy');
        }

        try {
            const data = await fetchJson(`${url}?campaign_id=${encodeURIComponent(campaignId)}`);
            if (current !== requestId) {
                return;
            }

            rows.innerHTML = renderRecipientRows(data.recipients || []);

            const campaign = data.campaign;
            if (!campaign) {
                if (fromRefresh) {
                    setRefreshState(refreshButtons, 'done');
                }
                return;
            }

            if (heading) {
                heading.textContent = `Send to ${formatNumber(campaign.total_recipients)} recipients`;
            }

            if (moreLink instanceof HTMLAnchorElement && campaign.stats_url) {
                moreLink.href = campaign.stats_url;
                moreLink.classList.remove('pointer-events-none', 'opacity-50');
            }

            applyCampaignMetrics(root, campaign);

            if (fromRefresh) {
                flashRefreshTarget(root.querySelector('[data-campaign-review-metrics]') || rows);
                setRefreshState(refreshButtons, 'done');
            }
        } catch (error) {
            console.warn('Campaign review refresh failed', error);
            if (fromRefresh && current === requestId) {
                setRefreshState(refreshButtons, 'error');
            }
        } finally {
            if (!fromRefresh && current === requestId) {
                setRefreshState(refreshButtons, 'idle');
            }
        }
    };

    if (select instanceof HTMLSelectElement) {
        select.addEventListener('change', () => load());
    }
    refreshButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            load({ fromRefresh: true });
        });
    });
}

export function initDashboard() {
    document.querySelectorAll('[data-dashboard-credits]').forEach((root) => {
        initCreditsFilter(root);
    });

    document.querySelectorAll('[data-dashboard-campaign-review]').forEach((root) => {
        initCampaignReview(root);
    });
}
