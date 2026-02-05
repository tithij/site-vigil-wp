(function() {
    'use strict';
    
    // Generate or retrieve session ID
    function getSessionId() {
        var sessionId = sessionStorage.getItem('site_vitals_session_id');
        
        if (!sessionId) {
            // Generate new session ID
            sessionId = 'sv_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            sessionStorage.setItem('site_vitals_session_id', sessionId);
        }
        
        return sessionId;
    }
    
    // Track visit
    function trackVisit() {
        var data = {
            action: 'site_vitals_track',
            nonce: siteVitalsData.nonce,
            session_id: getSessionId(),
            page: window.location.href,
            referrer: document.referrer || ''
        };
        
        // Use sendBeacon if available (best for page unload)
        if (navigator.sendBeacon) {
            var formData = new FormData();
            for (var key in data) {
                formData.append(key, data[key]);
            }
            navigator.sendBeacon(siteVitalsData.ajax_url, formData);
        } else {
            // Fallback to AJAX
            var xhr = new XMLHttpRequest();
            xhr.open('POST', siteVitalsData.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            var params = Object.keys(data).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }).join('&');
            
            xhr.send(params);
        }
    }
    
    // Track when page is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackVisit);
    } else {
        trackVisit();
    }
    
})();
