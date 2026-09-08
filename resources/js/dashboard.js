function statusChipClass(variant) {
    const map = {
        new: 'bg-stat-blue/15 text-stat-blue',
        scheduled: 'bg-stat-orange/15 text-stat-orange',
        done: 'bg-stat-emerald/15 text-stat-emerald',
        approved: 'bg-stat-emerald/15 text-stat-emerald',
        'fd-approved': 'bg-green-50 text-green-700',
        pending: 'bg-stat-orange/15 text-stat-orange',
        rejected: 'bg-danger/10 text-danger',
        active: 'bg-green-50 text-green-700',
        running: 'bg-teal-50 text-teal-700',
        sending: 'bg-teal-50 text-teal-700',
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

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return response.json();
}

function initCreditsFilter(root) {
    const select = root.querySelector('[data-credits-period]');
    const url = root.dataset.creditsUrl;
    if (!select || !url) {
        return;
    }

    let requestId = 0;

    const load = async () => {
        const period = select.value || 'daily';
        const current = ++requestId;
        updateUrlParam('credits_period', period);

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
                valueEl.textContent = `${used}/${limit}`;
            });
        } catch (error) {
            console.warn('Credits refresh failed', error);
        }
    };

    select.addEventListener('change', load);
    root.querySelectorAll('[data-credits-refresh]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            load();
        });
    });
}

function renderRecipientRows(recipients) {
    if (!Array.isArray(recipients) || recipients.length === 0) {
        return `<tr class="border-t border-divider bg-elevated">
            <td colspan="6" class="fd-table-cell p-4 text-sm text-text-muted">No recipient logs for this campaign yet.</td>
        </tr>`;
    }

    return recipients
        .map((row, index) => {
            const si = String(index + 1).padStart(2, '0');
            const chip = statusChipClass(row.status_variant);

            return `<tr class="border-t border-divider bg-elevated">
                <td class="fd-table-cell p-2 pl-4">${si}</td>
                <td class="fd-table-name p-2">${escapeHtml(row.name || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.phone || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.campaign || '—')}</td>
                <td class="fd-table-cell p-2 text-xs">${escapeHtml(row.sent_at || '—')}</td>
                <td class="p-2 text-center">
                    <span class="fd-status-chip inline-flex items-center rounded px-2 py-1 ${chip}">${escapeHtml(row.status_label || 'Pending')}</span>
                </td>
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

function initCampaignReview(root) {
    const select = root.querySelector('[data-campaign-review-select]');
    const url = root.dataset.campaignReviewUrl;
    const rows = root.querySelector('[data-campaign-review-rows]');
    const heading = root.querySelector('[data-campaign-review-heading]');
    const moreLink = root.querySelector('[data-campaign-review-more]');

    if (!select || !url || !rows) {
        return;
    }

    let requestId = 0;

    const load = async () => {
        const campaignId = select.value;
        if (!campaignId) {
            return;
        }

        const current = ++requestId;
        updateUrlParam('campaign_id', campaignId);

        try {
            const data = await fetchJson(`${url}?campaign_id=${encodeURIComponent(campaignId)}`);
            if (current !== requestId) {
                return;
            }

            rows.innerHTML = renderRecipientRows(data.recipients || []);

            const campaign = data.campaign;
            if (!campaign) {
                return;
            }

            if (heading) {
                heading.textContent = `Send to ${formatNumber(campaign.total_recipients)} recipients`;
            }

            if (moreLink instanceof HTMLAnchorElement && campaign.stats_url) {
                moreLink.href = campaign.stats_url;
                moreLink.classList.remove('pointer-events-none', 'opacity-50');
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
        } catch (error) {
            console.warn('Campaign review refresh failed', error);
        }
    };

    select.addEventListener('change', load);
    root.querySelectorAll('[data-campaign-review-refresh]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            load();
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
