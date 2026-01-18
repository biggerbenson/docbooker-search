<?php
/**
 * Plugin Name: DocBooker Frontend Search (BookingID-only)
 * Plugin URI: https://dchamplegacy.com/docbooker-search
 * Description: Frontend search for DocBooker bookings. STRICT booking ID search only. Displays Booking ID, Patient Name, Status and Present Status with pill styling.
 * Version: 1.2
 * Author: Dchamp legacy
 * Author URI: https://dchamplegacy.com
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueue front-end assets
 */
add_action('wp_enqueue_scripts', function(){
    $dir = plugin_dir_url(__FILE__);

    wp_enqueue_script(
        'docbooker-search-js',
        $dir . 'docbooker-search.js',
        ['jquery'],
        '1.2',
        true
    );

    wp_localize_script('docbooker-search-js', 'DocBookerSearch', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('docbooker_search_nonce'),
        'example'  => '#WPDB-5932',
    ]);

    wp_enqueue_style(
        'docbooker-search-css',
        $dir . 'docbooker-search.css',
        [],
        '1.2'
    );
});

/**
 * Shortcode to render search box
 */
add_shortcode('docbooker_search', function($atts){
    ob_start(); ?>
    <div class="dbk-search-wrap">
      <div class="dbk-controls">
        <input
          id="dbk-search-input"
          type="text"
          placeholder="Enter Booking ID (e.g. #WPDB-5932)"
          aria-label="Booking ID"
        />
        <button id="dbk-search-btn" type="button">Search</button>
        <button id="dbk-reset-btn" type="button">Reset</button>
      </div>
      <div id="dbk-results" aria-live="polite"></div>
      <div class="dbk-note">
        Use <strong>Booking ID only</strong>, for example <code>#WPDB-5932</code>.
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * AJAX handlers (logged in and guests)
 */
add_action('wp_ajax_docbooker_search',        'docbooker_search_handler');
add_action('wp_ajax_nopriv_docbooker_search', 'docbooker_search_handler');

function docbooker_search_handler(){
    check_ajax_referer('docbooker_search_nonce','nonce');

    if ( ! isset($_POST['q']) ) {
        wp_send_json_error('No query provided');
    }

    global $wpdb;

    $q_raw = wp_unslash($_POST['q']);
    $q     = trim(sanitize_text_field($q_raw));

    if ($q === '') {
        wp_send_json_error('Query empty');
    }

    // STRICT: only allow #, letters, numbers, hyphen (no spaces, no @, no name/email)
    if (!preg_match('/^[#A-Za-z0-9\-]+$/', $q)) {
        wp_send_json_success([
            'found'   => false,
            'items'   => [],
            'message' => 'Please search using Booking ID only (e.g. #WPDB-5932).',
        ]);
    }

    // Your actual tables based on screenshots:
    //   wpfw_wpddb_bookings  (bookings)
    //   wpfw_wpddb_patients  (patients with full_name)
    $booking_table = $wpdb->prefix . 'wpddb_bookings';
    $patient_table = $wpdb->prefix . 'wpddb_patients';

    // Optional: make sure the tables exist (avoids fatal errors on other sites)
    $booking_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $booking_table)
    );
    $patient_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $patient_table)
    );

    if ( ! $booking_exists ) {
        wp_send_json_error('Bookings table not found.');
    }

    // Search by booking_id only
    $like = '%' . $wpdb->esc_like($q) . '%';

    if ($patient_exists) {
        // Join with patients table to get full_name
        $sql = "
            SELECT
              b.booking_id             AS booking_id,
              p.full_name              AS patient_name,
              b.status                 AS status,
              b.booking_present_status AS present_status
            FROM {$booking_table} AS b
            LEFT JOIN {$patient_table} AS p
                ON b.patient_id = p.id
            WHERE b.booking_id LIKE %s
            LIMIT 50
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $like), ARRAY_A);
    } else {
        // Fallback: no patients table, still return bookings
        $sql = "
            SELECT
              b.booking_id             AS booking_id,
              ''                       AS patient_name,
              b.status                 AS status,
              b.booking_present_status AS present_status
            FROM {$booking_table} AS b
            WHERE b.booking_id LIKE %s
            LIMIT 50
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $like), ARRAY_A);
    }

    if ($rows === null) {
        wp_send_json_error('DB error.');
    }

    if (empty($rows)) {
        wp_send_json_success([
            'found'   => false,
            'items'   => [],
            'message' => 'No booking found for that Booking ID.',
        ]);
    }

    wp_send_json_success([
        'found' => true,
        'items' => $rows,
    ]);
}

