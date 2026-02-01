/**
 * Portal JavaScript - Shared utilities for the client portal
 */

// API base URL - adjust if needed for your hosting environment
const API_BASE = 'api/';

// Mobile hamburger menu toggle
document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('nav-links');

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // Close menu when a link is clicked
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
            }
        });
    }
});

/**
 * Check if user is authenticated
 * @returns {Promise<Object|null>} User data if authenticated, null otherwise
 */
async function checkAuth() {
    try {
        const response = await fetch(API_BASE + 'session-check.php', {
            method: 'GET',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.client) {
            return data.client;
        }
        return null;
    } catch (error) {
        console.error('Auth check failed:', error);
        return null;
    }
}

/**
 * Redirect to login if not authenticated
 * For use on protected pages
 */
async function requireAuth() {
    const user = await checkAuth();
    if (!user) {
        window.location.href = 'login.html';
        return null;
    }
    return user;
}

/**
 * Redirect to portal if already authenticated
 * For use on login/register pages
 */
async function redirectIfAuthenticated() {
    const user = await checkAuth();
    if (user) {
        window.location.href = 'portal.html';
        return true;
    }
    return false;
}

/**
 * Logout the user
 */
async function logout() {
    try {
        await fetch(API_BASE + 'logout.php', {
            method: 'POST',
            credentials: 'include'
        });
    } catch (error) {
        // Continue with logout even if request fails
    }
    window.location.href = 'login.html';
}

/**
 * Make an authenticated API request
 * @param {string} endpoint - API endpoint
 * @param {Object} options - Fetch options
 * @returns {Promise<Object>} API response data
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        }
    };

    const response = await fetch(API_BASE + endpoint, {
        ...defaultOptions,
        ...options
    });

    return response.json();
}

/**
 * Show/hide loading state on a button
 * @param {HTMLElement} button - Button element
 * @param {boolean} loading - Whether to show loading state
 * @param {string} loadingText - Text to show while loading
 * @param {string} normalText - Text to show when not loading
 */
function setButtonLoading(button, loading, loadingText = 'Loading...', normalText = 'Submit') {
    const textSpan = button.querySelector('.button-text');
    const loaderSpan = button.querySelector('.button-loader');

    if (loading) {
        if (textSpan) textSpan.textContent = loadingText;
        if (loaderSpan) loaderSpan.style.display = 'inline-block';
        button.disabled = true;
    } else {
        if (textSpan) textSpan.textContent = normalText;
        if (loaderSpan) loaderSpan.style.display = 'none';
        button.disabled = false;
    }
}

/**
 * Show an error message
 * @param {HTMLElement} element - Error display element
 * @param {string} message - Error message
 */
function showError(element, message) {
    element.textContent = message;
    element.style.display = 'block';
}

/**
 * Hide an error message
 * @param {HTMLElement} element - Error display element
 */
function hideError(element) {
    element.textContent = '';
    element.style.display = 'none';
}
