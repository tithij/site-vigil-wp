/* Site Vitals Admin JavaScript */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Auto-refresh dashboard stats every 30 seconds
        if ($('.site-vitals-dashboard').length) {
            setInterval(function() {
                location.reload();
            }, 30000);
        }
    });
    
})(jQuery);
