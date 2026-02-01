/**
 * Dashboard JavaScript
 * Handles website metrics display and refresh functionality
 * Enhanced with SSL, response time graph, and incidents
 */

// State management
const state = {
    websites: [],
    metrics: {},
    isLoading: true,
    refreshingAll: false
};

// DOM Elements
const elements = {
    loading: document.getElementById('dashboard-loading'),
    content: document.getElementById('dashboard-content'),
    websitesGrid: document.getElementById('websites-grid'),
    noWebsites: document.getElementById('no-websites'),
    refreshAllBtn: document.getElementById('refresh-all-btn'),
    logoutBtn: document.getElementById('logout-btn')
};

// Initialize dashboard
document.addEventListener('DOMContentLoaded', initDashboard);

async function initDashboard() {
    try {
        // Check authentication
        const authResponse = await fetch('api/session-check.php', {
            method: 'GET',
            credentials: 'include'
        });
        const authData = await authResponse.json();

        if (!authData.success || !authData.client) {
            window.location.href = 'login.html';
            return;
        }

        // Load websites
        await loadWebsites();

        // Show content
        elements.loading.style.display = 'none';
        elements.content.style.display = 'block';

        // Setup event listeners
        setupEventListeners();

    } catch (error) {
        console.error('Dashboard initialization error:', error);
        window.location.href = 'login.html';
    }
}

async function loadWebsites() {
    try {
        const response = await fetch('api/websites.php', {
            method: 'GET',
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            state.websites = data.websites || [];
            renderWebsites();

            // Load metrics for each website
            if (state.websites.length > 0) {
                await Promise.all(state.websites.map(website => loadMetrics(website.id)));
            }
        } else {
            console.error('Failed to load websites:', data.error);
        }
    } catch (error) {
        console.error('Error loading websites:', error);
    }
}

async function loadMetrics(websiteId) {
    try {
        const response = await fetch(`api/metrics.php?website_id=${websiteId}`, {
            method: 'GET',
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success) {
            state.metrics[websiteId] = data.metrics;
            updateWebsiteCard(websiteId, data.metrics, data.refresh);
        }
    } catch (error) {
        console.error(`Error loading metrics for website ${websiteId}:`, error);
    }
}

function renderWebsites() {
    if (state.websites.length === 0) {
        elements.websitesGrid.style.display = 'none';
        elements.noWebsites.style.display = 'block';
        elements.refreshAllBtn.style.display = 'none';
        return;
    }

    elements.noWebsites.style.display = 'none';
    elements.websitesGrid.style.display = 'grid';

    elements.websitesGrid.innerHTML = state.websites.map(website => `
        <div class="website-card" data-website-id="${website.id}">
            <div class="website-header">
                <div class="website-info">
                    <h3 class="website-name">${escapeHtml(website.name)}</h3>
                    <span class="website-url">
                        <a href="${escapeHtml(website.url)}" target="_blank" rel="noopener noreferrer">
                            ${formatUrl(website.url)}
                        </a>
                    </span>
                </div>
                <button class="refresh-btn" data-website-id="${website.id}" title="Refresh metrics">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 4v6h-6"></path>
                        <path d="M1 20v-6h6"></path>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                </button>
            </div>

            <div class="metrics-grid">
                <div class="metric-card loading" data-metric="status">
                    <span class="metric-label">Status</span>
                    <span class="metric-value">—</span>
                </div>
                <div class="metric-card loading" data-metric="uptime">
                    <span class="metric-label">Uptime (30d)</span>
                    <span class="metric-value">—</span>
                </div>
                <div class="metric-card loading" data-metric="performance">
                    <span class="metric-label">Performance</span>
                    <span class="metric-value">—</span>
                </div>
                <div class="metric-card loading" data-metric="response">
                    <span class="metric-label">Response</span>
                    <span class="metric-value">—</span>
                </div>
                <div class="metric-card loading ssl-card" data-metric="ssl">
                    <span class="metric-label">SSL Certificate</span>
                    <span class="metric-value">—</span>
                </div>
            </div>

            <!-- Details Grid: Response Graph + Incidents -->
            <div class="details-grid" data-details="${website.id}">
                <div class="details-panel response-graph-section">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3v18h18"></path>
                            <path d="M18 9l-5 5-4-4-3 3"></path>
                        </svg>
                        Response Time
                    </div>
                    <div class="response-graph" data-graph="${website.id}">
                        <div style="color: var(--silver); font-size: 0.9rem; margin: auto;">Loading...</div>
                    </div>
                </div>

                <div class="details-panel incidents-section">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 8v4l3 3"></path>
                            <circle cx="12" cy="12" r="10"></circle>
                        </svg>
                        Recent Incidents
                    </div>
                    <div class="incidents-list" data-incidents="${website.id}">
                        <div style="color: var(--silver); font-size: 0.9rem; text-align: center; padding: 1rem;">Loading...</div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <span class="last-updated">Loading...</span>
            </div>
        </div>
    `).join('');
}

function updateWebsiteCard(websiteId, metrics, refreshInfo) {
    const card = document.querySelector(`.website-card[data-website-id="${websiteId}"]`);
    if (!card) return;

    const uptime = metrics.uptime || {};
    const performance = metrics.performance || {};

    // Update status
    const statusCard = card.querySelector('[data-metric="status"]');
    if (statusCard) {
        statusCard.classList.remove('loading');
        const statusText = uptime.available ? capitalizeFirst(uptime.status || 'unknown') : 'N/A';
        const statusClass = uptime.available ? `status-${uptime.status || 'unknown'}` : 'status-unknown';
        statusCard.innerHTML = `
            <span class="metric-label">Status</span>
            <span class="metric-value ${statusClass}">${statusText}</span>
        `;
    }

    // Update uptime percentage
    const uptimeCard = card.querySelector('[data-metric="uptime"]');
    if (uptimeCard) {
        uptimeCard.classList.remove('loading');
        const uptimeValue = uptime.available ? `${uptime.uptime_30d || 0}%` : 'N/A';
        uptimeCard.innerHTML = `
            <span class="metric-label">Uptime (30d)</span>
            <span class="metric-value">${uptimeValue}</span>
        `;
    }

    // Update performance score
    const perfCard = card.querySelector('[data-metric="performance"]');
    if (perfCard) {
        perfCard.classList.remove('loading');
        if (performance.available) {
            const score = performance.score || 0;
            const scoreClass = getScoreClass(score);
            perfCard.innerHTML = `
                <span class="metric-label">Performance</span>
                <div class="score-circle ${scoreClass}">${score}</div>
            `;
        } else {
            perfCard.innerHTML = `
                <span class="metric-label">Performance</span>
                <span class="metric-value status-unknown">N/A</span>
            `;
        }
    }

    // Update response time
    const responseCard = card.querySelector('[data-metric="response"]');
    if (responseCard) {
        responseCard.classList.remove('loading');
        const responseTime = uptime.available ? `${uptime.response_time_avg || 0}ms` : 'N/A';
        responseCard.innerHTML = `
            <span class="metric-label">Response</span>
            <span class="metric-value">${responseTime}</span>
        `;
    }

    // Update SSL certificate
    const sslCard = card.querySelector('[data-metric="ssl"]');
    if (sslCard) {
        sslCard.classList.remove('loading');
        sslCard.className = 'metric-card ssl-card';

        if (uptime.ssl) {
            const ssl = uptime.ssl;
            const sslStatusClass = `ssl-${ssl.status}`;
            sslCard.classList.add(sslStatusClass);

            let sslIcon = '🔒';
            let sslText = 'Valid';

            if (ssl.status === 'expired') {
                sslIcon = '🔓';
                sslText = 'Expired';
            } else if (ssl.status === 'critical') {
                sslIcon = '⚠️';
                sslText = `Expires in ${ssl.days_until_expiry}d`;
            } else if (ssl.status === 'warning') {
                sslIcon = '⚠️';
                sslText = `Expires in ${ssl.days_until_expiry}d`;
            } else if (ssl.days_until_expiry) {
                sslText = `${ssl.days_until_expiry} days`;
            }

            sslCard.innerHTML = `
                <div class="ssl-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="ssl-info">
                    <div class="ssl-status">${sslText}</div>
                    <div class="ssl-detail">${ssl.issuer || 'SSL Certificate'}</div>
                </div>
            `;
        } else {
            sslCard.innerHTML = `
                <span class="metric-label">SSL Certificate</span>
                <span class="metric-value status-unknown">N/A</span>
            `;
        }
    }

    // Update response time graph
    const graphContainer = card.querySelector(`[data-graph="${websiteId}"]`);
    if (graphContainer && uptime.response_time_history && uptime.response_time_history.length > 0) {
        renderResponseGraph(graphContainer, uptime.response_time_history);
    } else if (graphContainer) {
        graphContainer.innerHTML = `
            <div class="no-incidents">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3v18h18"></path>
                    <path d="M18 9l-5 5-4-4-3 3"></path>
                </svg>
                <p>No response data available</p>
            </div>
        `;
    }

    // Update incidents list
    const incidentsContainer = card.querySelector(`[data-incidents="${websiteId}"]`);
    if (incidentsContainer) {
        renderIncidents(incidentsContainer, uptime.incidents || []);
    }

    // Update last updated
    const footer = card.querySelector('.card-footer');
    if (footer) {
        const cachedAt = uptime.cached_at || performance.cached_at;
        const isStale = uptime.is_stale || performance.is_stale;
        let footerHtml = `<span class="last-updated">${formatTimeAgo(cachedAt)}</span>`;

        if (isStale) {
            footerHtml += `
                <span class="stale-indicator">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    </svg>
                    Data may be outdated
                </span>
            `;
        }

        if (refreshInfo && !refreshInfo.allowed) {
            footerHtml += `
                <span class="stale-indicator">
                    Rate limit: ${refreshInfo.remaining} refreshes left
                </span>
            `;
        }

        footer.innerHTML = footerHtml;
    }

    // Update refresh button state
    const refreshBtn = card.querySelector('.refresh-btn');
    if (refreshBtn && refreshInfo) {
        refreshBtn.disabled = !refreshInfo.allowed;
        refreshBtn.title = refreshInfo.allowed
            ? 'Refresh metrics'
            : `Rate limited. ${refreshInfo.remaining} refreshes remaining.`;
    }
}

function renderResponseGraph(container, history) {
    if (!history || history.length === 0) {
        container.innerHTML = '<div style="color: var(--silver); margin: auto;">No data</div>';
        return;
    }

    const maxValue = Math.max(...history.map(h => h.value));
    const minValue = Math.min(...history.map(h => h.value));

    const bars = history.map(point => {
        const heightPercent = maxValue > 0 ? (point.value / maxValue) * 100 : 0;
        return `<div class="graph-bar" style="height: ${Math.max(heightPercent, 5)}%" data-value="${point.value}ms at ${point.time}"></div>`;
    }).join('');

    container.innerHTML = bars;
}

function renderIncidents(container, incidents) {
    if (!incidents || incidents.length === 0) {
        container.innerHTML = `
            <div class="no-incidents">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <p>No recent incidents</p>
            </div>
        `;
        return;
    }

    const incidentHtml = incidents.slice(0, 5).map(incident => {
        const isDown = incident.type === 'down';
        const icon = isDown
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';

        return `
            <div class="incident-item incident-${incident.type}">
                <div class="incident-icon">${icon}</div>
                <div class="incident-info">
                    <div class="incident-status">${isDown ? 'Went Down' : 'Came Back Up'}</div>
                    <div class="incident-time">${incident.datetime_formatted}</div>
                </div>
                ${incident.duration ? `<div class="incident-duration">Duration: ${incident.duration}</div>` : ''}
            </div>
        `;
    }).join('');

    container.innerHTML = incidentHtml;
}

function setupEventListeners() {
    // Refresh all button
    elements.refreshAllBtn.addEventListener('click', refreshAllWebsites);

    // Individual refresh buttons (using event delegation)
    elements.websitesGrid.addEventListener('click', (e) => {
        const refreshBtn = e.target.closest('.refresh-btn');
        if (refreshBtn) {
            const websiteId = parseInt(refreshBtn.dataset.websiteId);
            refreshWebsite(websiteId, refreshBtn);
        }
    });

    // Logout button
    elements.logoutBtn.addEventListener('click', async () => {
        try {
            await fetch('api/logout.php', {
                method: 'POST',
                credentials: 'include'
            });
        } catch (error) {
            // Logout anyway
        }
        window.location.href = 'login.html';
    });
}

async function refreshWebsite(websiteId, button) {
    if (button.disabled) return;

    // Show loading state
    button.classList.add('refreshing');
    button.disabled = true;

    // Set metric cards to loading
    const card = document.querySelector(`.website-card[data-website-id="${websiteId}"]`);
    if (card) {
        card.querySelectorAll('.metric-card').forEach(c => c.classList.add('loading'));
    }

    try {
        const response = await fetch('api/refresh-metrics.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ website_id: websiteId })
        });

        const data = await response.json();

        if (response.status === 429) {
            alert(data.error || 'Rate limit exceeded. Please wait before refreshing again.');
        } else if (data.success || data.metrics) {
            state.metrics[websiteId] = data.metrics;
            updateWebsiteCard(websiteId, data.metrics, data.refresh);
        } else {
            alert(data.error || 'Failed to refresh metrics');
        }
    } catch (error) {
        console.error('Error refreshing metrics:', error);
        alert('Failed to refresh metrics. Please try again.');
    } finally {
        button.classList.remove('refreshing');
        // Re-enable will be handled by updateWebsiteCard based on rate limit
    }
}

async function refreshAllWebsites() {
    if (state.refreshingAll || state.websites.length === 0) return;

    state.refreshingAll = true;
    elements.refreshAllBtn.classList.add('refreshing');
    elements.refreshAllBtn.disabled = true;

    for (const website of state.websites) {
        const button = document.querySelector(`.refresh-btn[data-website-id="${website.id}"]`);
        if (button && !button.disabled) {
            await refreshWebsite(website.id, button);
            // Small delay between requests
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    state.refreshingAll = false;
    elements.refreshAllBtn.classList.remove('refreshing');
    elements.refreshAllBtn.disabled = false;
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatUrl(url) {
    try {
        const urlObj = new URL(url);
        return urlObj.hostname;
    } catch {
        return url;
    }
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function getScoreClass(score) {
    if (score >= 90) return 'score-good';
    if (score >= 50) return 'score-moderate';
    return 'score-poor';
}

function formatTimeAgo(dateString) {
    if (!dateString) return 'Never updated';

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Updated just now';
    if (diffMins < 60) return `Updated ${diffMins} min ago`;
    if (diffHours < 24) return `Updated ${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    return `Updated ${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
}
