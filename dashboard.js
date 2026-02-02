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

// ==========================================
// Tab Switching & Analytics/SEO Functionality
// ==========================================

// Extended state for analytics and SEO
state.currentTab = 'monitoring';
state.selectedWebsiteId = null;
state.analyticsPeriod = '7d';
state.analyticsData = null;
state.seoData = null;
state.loadingAnalytics = false;
state.loadingSeo = false;

// Extended DOM elements
const tabElements = {
    tabs: document.querySelectorAll('.tab-btn'),
    tabContents: document.querySelectorAll('.tab-content'),
    websiteSelector: document.getElementById('website-selector'),
    periodSelector: document.getElementById('period-selector'),
    analyticsContent: document.getElementById('analytics-content'),
    analyticsLoading: document.getElementById('analytics-loading'),
    seoContent: document.getElementById('seo-content'),
    seoLoading: document.getElementById('seo-loading'),
    runAuditBtn: document.getElementById('run-audit-btn')
};

// Initialize tabs after DOM is ready
function initTabs() {
    // Tab switching
    tabElements.tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    // Website selector
    if (tabElements.websiteSelector) {
        tabElements.websiteSelector.addEventListener('change', (e) => {
            state.selectedWebsiteId = parseInt(e.target.value) || null;
            if (state.currentTab === 'analytics') {
                loadAnalytics();
            } else if (state.currentTab === 'seo') {
                loadSeoAudit();
            }
        });
    }

    // Period selector
    if (tabElements.periodSelector) {
        tabElements.periodSelector.addEventListener('change', (e) => {
            state.analyticsPeriod = e.target.value;
            loadAnalytics();
        });
    }

    // Run audit button
    if (tabElements.runAuditBtn) {
        tabElements.runAuditBtn.addEventListener('click', runSeoAudit);
    }

    // Populate website selector
    populateWebsiteSelector();
}

function populateWebsiteSelector() {
    if (!tabElements.websiteSelector) return;

    if (state.websites.length === 0) {
        tabElements.websiteSelector.innerHTML = '<option value="">No websites available</option>';
        return;
    }

    tabElements.websiteSelector.innerHTML = state.websites.map(website =>
        `<option value="${website.id}">${escapeHtml(website.name)}</option>`
    ).join('');

    // Select first website by default
    state.selectedWebsiteId = state.websites[0].id;
}

function switchTab(tabName) {
    state.currentTab = tabName;

    // Update tab buttons
    tabElements.tabs.forEach(tab => {
        tab.classList.toggle('active', tab.dataset.tab === tabName);
    });

    // Update tab content
    tabElements.tabContents.forEach(content => {
        content.classList.toggle('active', content.id === `${tabName}-tab`);
    });

    // Load data for the selected tab
    if (tabName === 'analytics' && state.selectedWebsiteId) {
        loadAnalytics();
    } else if (tabName === 'seo' && state.selectedWebsiteId) {
        loadSeoAudit();
    }
}

// ==========================================
// Analytics Functions
// ==========================================

async function loadAnalytics() {
    if (!state.selectedWebsiteId || state.loadingAnalytics) return;

    state.loadingAnalytics = true;

    // Show loading, hide content
    if (tabElements.analyticsLoading) tabElements.analyticsLoading.style.display = 'flex';
    if (tabElements.analyticsContent) tabElements.analyticsContent.style.display = 'none';

    try {
        const response = await fetch(
            `api/analytics.php?website_id=${state.selectedWebsiteId}&period=${state.analyticsPeriod}`,
            { method: 'GET', credentials: 'include' }
        );
        const data = await response.json();

        if (data.success) {
            state.analyticsData = data;
            renderAnalytics(data);
        } else {
            showAnalyticsError(data.error || 'Failed to load analytics');
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
        showAnalyticsError('Failed to load analytics data');
    } finally {
        state.loadingAnalytics = false;
        if (tabElements.analyticsLoading) tabElements.analyticsLoading.style.display = 'none';
        if (tabElements.analyticsContent) tabElements.analyticsContent.style.display = 'block';
    }
}

function showAnalyticsError(message) {
    if (tabElements.analyticsContent) {
        tabElements.analyticsContent.innerHTML = `
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>Unable to Load Analytics</h3>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }
}

function renderAnalytics(data) {
    const overview = data.overview || {};
    const timeline = data.timeline || [];
    const topPages = data.top_pages || [];
    const sources = data.traffic_sources || [];
    const devices = data.devices || [];
    const browsers = data.browsers || [];

    // Update overview stats
    updateAnalyticsOverview(overview);

    // Render charts and lists
    renderTimelineChart(timeline);
    renderTopPages(topPages);
    renderTrafficSources(sources);
    renderDeviceBreakdown(devices);
    renderBrowserList(browsers);
}

function updateAnalyticsOverview(overview) {
    const pageviewsEl = document.getElementById('stat-pageviews');
    const visitorsEl = document.getElementById('stat-visitors');
    const avgTimeEl = document.getElementById('stat-avg-time');
    const bounceEl = document.getElementById('stat-bounce');

    if (pageviewsEl) pageviewsEl.textContent = formatNumber(overview.pageviews || overview.total_pageviews || 0);
    if (visitorsEl) visitorsEl.textContent = formatNumber(overview.unique_visitors || 0);
    if (avgTimeEl) avgTimeEl.textContent = formatDuration(overview.avg_session_duration || 0);
    if (bounceEl) bounceEl.textContent = `${overview.bounce_rate || 0}%`;
}

function renderTimelineChart(timeline) {
    const container = document.getElementById('timeline-chart');
    if (!container) return;

    if (!timeline || timeline.length === 0) {
        container.innerHTML = '<div class="chart-empty">No data for this period</div>';
        return;
    }

    const maxValue = Math.max(...timeline.map(t => t.pageviews || 0));
    const chartHeight = 150;

    const barsHtml = timeline.map(point => {
        const height = maxValue > 0 ? ((point.pageviews || 0) / maxValue) * chartHeight : 0;
        const label = formatChartDate(point.date_label || point.date);
        return `
            <div class="chart-bar-wrapper" title="${label}: ${point.pageviews || 0} pageviews">
                <div class="chart-bar" style="height: ${Math.max(height, 2)}px"></div>
                <span class="chart-label">${label}</span>
            </div>
        `;
    }).join('');

    container.innerHTML = `<div class="chart-bars">${barsHtml}</div>`;
}

function renderTopPages(pages) {
    const container = document.getElementById('top-pages-list');
    if (!container) return;

    if (!pages || pages.length === 0) {
        container.innerHTML = '<div class="data-list-empty">No page data available</div>';
        return;
    }

    const maxViews = pages[0]?.views || 1;

    container.innerHTML = pages.slice(0, 10).map(page => {
        const percentage = Math.round((page.views / maxViews) * 100);
        const url = page.path || page.page_path || page.page_url || '/';
        const title = page.page_title || url;

        return `
            <div class="data-list-item">
                <div class="item-bar" style="width: ${percentage}%"></div>
                <div class="item-content">
                    <span class="item-label" title="${escapeHtml(url)}">${escapeHtml(truncateUrl(title, 40))}</span>
                    <span class="item-value">${formatNumber(page.views)}</span>
                </div>
            </div>
        `;
    }).join('');
}

function renderTrafficSources(sources) {
    const container = document.getElementById('traffic-sources-list');
    if (!container) return;

    if (!sources || sources.length === 0) {
        container.innerHTML = '<div class="data-list-empty">No traffic source data</div>';
        return;
    }

    const maxViews = sources[0]?.visits || 1;

    container.innerHTML = sources.slice(0, 10).map(source => {
        const percentage = Math.round((source.visits / maxViews) * 100);
        const label = source.source || 'Direct';

        return `
            <div class="data-list-item">
                <div class="item-bar" style="width: ${percentage}%"></div>
                <div class="item-content">
                    <span class="item-label">${escapeHtml(label)}</span>
                    <span class="item-value">${formatNumber(source.visits)}</span>
                </div>
            </div>
        `;
    }).join('');
}

function renderDeviceBreakdown(devices) {
    const container = document.getElementById('device-breakdown');
    if (!container) return;

    // Handle both array format (from API) and object format
    let deviceData = { desktop: 0, mobile: 0, tablet: 0 };

    if (Array.isArray(devices)) {
        // API returns: [{device_type: "desktop", views: 13, percentage: "100.0"}]
        devices.forEach(d => {
            if (d.device_type && deviceData.hasOwnProperty(d.device_type)) {
                deviceData[d.device_type] = d.views || 0;
            }
        });
    } else if (typeof devices === 'object') {
        // Legacy object format: {desktop: X, mobile: Y, tablet: Z}
        deviceData = { ...deviceData, ...devices };
    }

    const total = deviceData.desktop + deviceData.mobile + deviceData.tablet;

    if (total === 0) {
        container.innerHTML = '<div class="chart-empty">No device data</div>';
        return;
    }

    const desktopPct = Math.round(deviceData.desktop / total * 100);
    const mobilePct = Math.round(deviceData.mobile / total * 100);
    const tabletPct = Math.round(deviceData.tablet / total * 100);

    container.innerHTML = `
        <div class="device-chart">
            <div class="device-bar">
                <div class="device-segment desktop" style="width: ${desktopPct}%" title="Desktop: ${desktopPct}%"></div>
                <div class="device-segment mobile" style="width: ${mobilePct}%" title="Mobile: ${mobilePct}%"></div>
                <div class="device-segment tablet" style="width: ${tabletPct}%" title="Tablet: ${tabletPct}%"></div>
            </div>
            <div class="device-legend">
                <span class="legend-item"><span class="legend-dot desktop"></span>Desktop ${desktopPct}%</span>
                <span class="legend-item"><span class="legend-dot mobile"></span>Mobile ${mobilePct}%</span>
                <span class="legend-item"><span class="legend-dot tablet"></span>Tablet ${tabletPct}%</span>
            </div>
        </div>
    `;
}

function renderBrowserList(browsers) {
    const container = document.getElementById('browser-list');
    if (!container) return;

    if (!browsers || browsers.length === 0) {
        container.innerHTML = '<div class="data-list-empty">No browser data</div>';
        return;
    }

    // API returns 'views', handle both 'views' and 'count' for compatibility
    const maxViews = browsers[0]?.views || browsers[0]?.count || 1;

    container.innerHTML = browsers.slice(0, 5).map(browser => {
        const views = browser.views || browser.count || 0;
        const percentage = Math.round((views / maxViews) * 100);

        return `
            <div class="data-list-item">
                <div class="item-bar" style="width: ${percentage}%"></div>
                <div class="item-content">
                    <span class="item-label">${escapeHtml(browser.browser || 'Unknown')}</span>
                    <span class="item-value">${formatNumber(views)}</span>
                </div>
            </div>
        `;
    }).join('');
}

// ==========================================
// SEO Audit Functions
// ==========================================

async function loadSeoAudit() {
    if (!state.selectedWebsiteId || state.loadingSeo) return;

    state.loadingSeo = true;

    // Show loading, hide content
    if (tabElements.seoLoading) tabElements.seoLoading.style.display = 'flex';
    if (tabElements.seoContent) tabElements.seoContent.style.display = 'none';

    try {
        const response = await fetch(
            `api/seo-audit.php?website_id=${state.selectedWebsiteId}`,
            { method: 'GET', credentials: 'include' }
        );
        const data = await response.json();

        if (data.success) {
            state.seoData = data;
            renderSeoAudit(data);
        } else {
            showSeoError(data.error || 'Failed to load SEO audit');
        }
    } catch (error) {
        console.error('Error loading SEO audit:', error);
        showSeoError('Failed to load SEO data');
    } finally {
        state.loadingSeo = false;
        if (tabElements.seoLoading) tabElements.seoLoading.style.display = 'none';
        if (tabElements.seoContent) tabElements.seoContent.style.display = 'block';
    }
}

async function runSeoAudit() {
    if (!state.selectedWebsiteId) return;

    const btn = tabElements.runAuditBtn;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Running Audit...';
    }

    try {
        const response = await fetch('api/seo-audit.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ website_id: state.selectedWebsiteId })
        });

        const data = await response.json();

        if (response.status === 429) {
            alert(data.error || 'An audit was recently run. Please wait before running another.');
        } else if (data.success) {
            state.seoData = data;
            renderSeoAudit(data);
        } else {
            alert(data.error || 'Failed to run SEO audit');
        }
    } catch (error) {
        console.error('Error running SEO audit:', error);
        alert('Failed to run SEO audit. Please try again.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6"></path>
                    <path d="M1 20v-6h6"></path>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Run New Audit
            `;
        }
    }
}

function showSeoError(message) {
    if (tabElements.seoContent) {
        tabElements.seoContent.innerHTML = `
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>Unable to Load SEO Data</h3>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }
}

function renderSeoAudit(data) {
    const audit = data.audit;
    const website = data.website;

    if (!audit) {
        renderNoAudit(website);
        return;
    }

    // Update overall score
    updateSeoScore(audit.overall_score || 0);

    // Update subscores
    updateSubscores({
        meta: audit.meta_score || 0,
        content: audit.content_score || 0,
        technical: audit.technical_score || 0,
        performance: audit.performance_score || 0
    });

    // Update issues list
    renderSeoIssues(audit.issues || []);

    // Update recommendations
    renderSeoRecommendations(audit.recommendations || []);

    // Update last audit time
    const lastAuditEl = document.getElementById('last-audit-time');
    if (lastAuditEl && audit.created_at) {
        lastAuditEl.textContent = formatTimeAgo(audit.created_at);
    }
}

function renderNoAudit(website) {
    if (tabElements.seoContent) {
        tabElements.seoContent.innerHTML = `
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>No SEO Audit Available</h3>
                <p>Run an SEO audit to see your website's health score and recommendations.</p>
                <button class="btn btn-primary" onclick="runSeoAudit()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Run SEO Audit
                </button>
            </div>
        `;
    }
}

function updateSeoScore(score) {
    const scoreEl = document.getElementById('seo-score-value');
    const ringEl = document.getElementById('seo-score-ring');
    const labelEl = document.getElementById('seo-score-label');

    if (scoreEl) scoreEl.textContent = score;

    if (ringEl) {
        // Calculate circumference and offset for circular progress
        const circumference = 2 * Math.PI * 54; // r=54
        const offset = circumference - (score / 100) * circumference;
        ringEl.style.strokeDasharray = circumference;
        ringEl.style.strokeDashoffset = offset;

        // Color based on score
        if (score >= 80) {
            ringEl.style.stroke = 'var(--sage)';
        } else if (score >= 50) {
            ringEl.style.stroke = 'var(--gold)';
        } else {
            ringEl.style.stroke = '#e74c3c';
        }
    }

    if (labelEl) {
        if (score >= 80) {
            labelEl.textContent = 'Good';
            labelEl.className = 'score-label good';
        } else if (score >= 50) {
            labelEl.textContent = 'Needs Work';
            labelEl.className = 'score-label moderate';
        } else {
            labelEl.textContent = 'Poor';
            labelEl.className = 'score-label poor';
        }
    }
}

function updateSubscores(scores) {
    const subscores = [
        { id: 'subscore-meta', value: scores.meta, label: 'Meta Tags' },
        { id: 'subscore-content', value: scores.content, label: 'Content' },
        { id: 'subscore-technical', value: scores.technical, label: 'Technical' },
        { id: 'subscore-performance', value: scores.performance, label: 'Performance' }
    ];

    subscores.forEach(sub => {
        const container = document.getElementById(sub.id);
        if (container) {
            const scoreClass = sub.value >= 80 ? 'good' : (sub.value >= 50 ? 'moderate' : 'poor');
            container.innerHTML = `
                <div class="subscore-bar">
                    <div class="subscore-fill ${scoreClass}" style="width: ${sub.value}%"></div>
                </div>
                <div class="subscore-info">
                    <span class="subscore-label">${sub.label}</span>
                    <span class="subscore-value">${sub.value}</span>
                </div>
            `;
        }
    });
}

function renderSeoIssues(issues) {
    const container = document.getElementById('seo-issues-list');
    if (!container) return;

    if (!issues || issues.length === 0) {
        container.innerHTML = `
            <div class="no-issues">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <p>No issues found!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = issues.map(issue => {
        const typeClass = issue.type || 'info';
        const icon = getIssueIcon(typeClass);

        return `
            <div class="issue-item issue-${typeClass}">
                <div class="issue-icon">${icon}</div>
                <div class="issue-message">${escapeHtml(issue.message)}</div>
            </div>
        `;
    }).join('');
}

function renderSeoRecommendations(recommendations) {
    const container = document.getElementById('seo-recommendations-list');
    if (!container) return;

    if (!recommendations || recommendations.length === 0) {
        container.innerHTML = '<p class="no-recommendations">No recommendations at this time.</p>';
        return;
    }

    container.innerHTML = recommendations.map(rec => `
        <div class="recommendation-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>${escapeHtml(rec)}</span>
        </div>
    `).join('');
}

function getIssueIcon(type) {
    switch (type) {
        case 'critical':
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>`;
        case 'warning':
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>`;
        default:
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>`;
    }
}

// ==========================================
// Additional Utility Functions
// ==========================================

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function formatDuration(seconds) {
    if (seconds < 60) {
        return `${Math.round(seconds)}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    return `${minutes}m ${secs}s`;
}

function formatChartDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function truncateUrl(url, maxLength) {
    if (url.length <= maxLength) return url;
    return url.substring(0, maxLength - 3) + '...';
}

// Initialize tabs after websites are loaded
const originalLoadWebsites = loadWebsites;
loadWebsites = async function() {
    await originalLoadWebsites();
    initTabs();
};
