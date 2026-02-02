/**
 * Diego Eduardo Analytics Tracker
 * Lightweight, privacy-friendly analytics
 *
 * Installation: Add to client's website before </body>
 * <script src="https://www.diegoheduardo.com/tracker.js" data-site="SITE_ID"></script>
 */
(function() {
    'use strict';

    // Get configuration from script tag
    var script = document.currentScript || (function() {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    var siteId = script.getAttribute('data-site');
    if (!siteId) {
        console.warn('Analytics: Missing data-site attribute');
        return;
    }

    var endpoint = 'https://www.diegoheduardo.com/api/track.php';

    // Generate or retrieve visitor ID (stored in localStorage)
    function getVisitorId() {
        var key = '_de_vid';
        var vid = localStorage.getItem(key);
        if (!vid) {
            vid = 'v_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            localStorage.setItem(key, vid);
        }
        return vid;
    }

    // Generate session ID (stored in sessionStorage)
    function getSessionId() {
        var key = '_de_sid';
        var sid = sessionStorage.getItem(key);
        if (!sid) {
            sid = 's_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            sessionStorage.setItem(key, sid);
        }
        return sid;
    }

    // Detect device type
    function getDeviceType() {
        var ua = navigator.userAgent;
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
            return 'tablet';
        }
        if (/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    // Get browser name
    function getBrowser() {
        var ua = navigator.userAgent;
        if (ua.indexOf('Firefox') > -1) return 'Firefox';
        if (ua.indexOf('SamsungBrowser') > -1) return 'Samsung';
        if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) return 'Opera';
        if (ua.indexOf('Trident') > -1) return 'IE';
        if (ua.indexOf('Edge') > -1) return 'Edge';
        if (ua.indexOf('Edg') > -1) return 'Edge';
        if (ua.indexOf('Chrome') > -1) return 'Chrome';
        if (ua.indexOf('Safari') > -1) return 'Safari';
        return 'Other';
    }

    // Get OS
    function getOS() {
        var ua = navigator.userAgent;
        if (ua.indexOf('Win') > -1) return 'Windows';
        if (ua.indexOf('Mac') > -1) return 'macOS';
        if (ua.indexOf('Linux') > -1) return 'Linux';
        if (ua.indexOf('Android') > -1) return 'Android';
        if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) return 'iOS';
        return 'Other';
    }

    // Extract domain from URL
    function getDomain(url) {
        if (!url) return null;
        try {
            var a = document.createElement('a');
            a.href = url;
            return a.hostname;
        } catch (e) {
            return null;
        }
    }

    // Get UTM parameters
    function getUTMParams() {
        var params = new URLSearchParams(window.location.search);
        return {
            source: params.get('utm_source'),
            medium: params.get('utm_medium'),
            campaign: params.get('utm_campaign')
        };
    }

    // Track page view
    function trackPageView() {
        var utm = getUTMParams();
        var referrer = document.referrer;
        var referrerDomain = getDomain(referrer);

        // Don't count internal referrers
        if (referrerDomain === window.location.hostname) {
            referrer = null;
            referrerDomain = null;
        }

        var data = {
            site: siteId,
            vid: getVisitorId(),
            sid: getSessionId(),
            url: window.location.href,
            path: window.location.pathname,
            title: document.title,
            ref: referrer,
            ref_domain: referrerDomain,
            utm_source: utm.source,
            utm_medium: utm.medium,
            utm_campaign: utm.campaign,
            device: getDeviceType(),
            browser: getBrowser(),
            os: getOS(),
            sw: window.screen.width,
            sh: window.screen.height,
            tz: Intl.DateTimeFormat().resolvedOptions().timeZone
        };

        // Send data
        if (navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, JSON.stringify(data));
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(data));
        }
    }

    // Track on page load
    if (document.readyState === 'complete') {
        trackPageView();
    } else {
        window.addEventListener('load', trackPageView);
    }

    // Track on SPA navigation (history changes)
    var pushState = history.pushState;
    history.pushState = function() {
        pushState.apply(history, arguments);
        setTimeout(trackPageView, 100);
    };

    window.addEventListener('popstate', function() {
        setTimeout(trackPageView, 100);
    });
})();
