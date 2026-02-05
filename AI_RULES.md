# AI Development Rules - Vital Statistics

## Tech Stack
- **PHP 7.4+**: Primary language using OOP and Singleton patterns for core components.
- **WordPress Plugin API**: Heavy reliance on hooks (actions/filters) for system integration.
- **MySQL / $wpdb**: Custom tables (`_sessions`, `_uptime`) for optimized data storage.
- **WordPress Cron**: Background processing for uptime checks and data cleanup.
- **AJAX API**: `admin-ajax.php` for real-time visitor tracking and dashboard updates.
- **jQuery**: Admin-side interactions and dashboard auto-refresh logic.
- **Vanilla JS**: Dependency-free frontend tracking to ensure zero impact on site speed.
- **Custom CSS**: Modern, responsive dashboard UI using a utility-first CSS approach.

## Library & Implementation Rules

### 1. Database & Persistence
- **Rule**: Use `$wpdb` for all database queries. Never use raw `mysqli` or `PDO`.
- **Rule**: Schema updates must go through `includes/class-database.php` using `dbDelta`.
- **Rule**: Use the WordPress Options API for settings and Transients for temporary state (e.g., alert cooldowns).

### 2. Security & Privacy
- **Rule**: All AJAX handlers must use `check_ajax_referer` with the `site_vitals_track` nonce.
- **Rule**: Sanitize all input data using `sanitize_text_field`, `esc_url_raw`, etc.
- **Rule**: Always respect the `anonymize_ip` setting in `class-tracker.php` for GDPR compliance.

### 3. Admin UI & Styling
- **Rule**: All admin styles must be prefixed with `sv-` to avoid conflicts with WordPress core or other plugins.
- **Rule**: Use the grid system (`sv-grid`, `sv-grid-3col`) defined in `assets/css/admin.css` for layout.
- **Rule**: Use Dashicons for simple icons and the custom status pill components for state indicators.

### 4. Performance
- **Rule**: Keep the frontend `tracker.js` as small as possible. Do not add external libraries to the frontend.
- **Rule**: Use `navigator.sendBeacon` for tracking when available to avoid blocking page navigation.
- **Rule**: Ensure heavy analytics queries in `class-analytics.php` are optimized with proper SQL indexes.

### 5. Code Structure
- **Rule**: Maintain the Singleton pattern for main controller classes (e.g., `Site_Vitals`, `Site_Vitals_Admin`).
- **Rule**: Follow WordPress PHP Coding Standards (tabs for indentation, snake_case for functions/variables).