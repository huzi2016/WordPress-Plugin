<?php
/**
 * Plugin Name: My Custom WC Account Ultimate <V2 class="1"></V2>
 * Description: Elegant solution: Fixes frontend button layout and displays membership code and phone in the native user list.
 * Version: 2.1
 * Author: Weiwei Chen
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. Core Data Logic
 */
function wc_ensure_member_data( $user_id ) {
    // Generate an 8-character unique membership code if it does not exist
    if ( ! get_user_meta( $user_id, 'membership_code', true ) ) {
        $code = strtoupper( wp_generate_password( 8, false ) );
        update_user_meta( $user_id, 'membership_code', $code );
    }
}

// Ensure data generation on customer creation
add_action( 'woocommerce_created_customer', 'wc_ensure_member_data' );

// Save phone field and ensure code during account details update
add_action( 'woocommerce_save_account_details', function($user_id){
    if(isset($_POST['custom_phone'])) {
        update_user_meta($user_id, 'custom_phone', sanitize_text_field($_POST['custom_phone']));
    }
    wc_ensure_member_data($user_id);
});

/**
 * 2. Frontend: Bottom positioning and name alignment (Verified layout solution)
 */
add_action( 'woocommerce_edit_account_form', function() {
    $user = wp_get_current_user();
    $code = get_user_meta( $user->ID, 'membership_code', true ) ?: 'Generating...';
    ?>
    <div class="custom-fields-v2" style="clear: both; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">
        <p class="form-row form-row-wide">
            <label> Membership Code </label>
            <input type="text" value="<?php echo $code; ?>" readonly style="background:#f4f4f4; border:1px solid #ddd;" />
        </p>
        <p class="form-row form-row-wide">
            <label for="custom_phone">Phone Number</label>
            <input type="text" name="custom_phone" id="custom_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'custom_phone', true)); ?>" />
        </p>
    </div>

    <style>
        /* Force block layout for the form */
        .woocommerce-EditAccountForm { display: block !important; }
        
        /* Precision fix for name field alignment */
        .woocommerce-EditAccountForm .form-row-first { width: 47% !important; float: left !important; margin-right: 3% !important; clear: none !important; }
        .woocommerce-EditAccountForm .form-row-last { width: 50% !important; float: left !important; clear: none !important; }
        
        /* Force button to the bottom with float isolation */
        .woocommerce-EditAccountForm button.button.save_account_details { 
            display: block !important; clear: both !important; float: none !important; margin: 50px 0 20px 0 !important; position: relative !important;
        }
    </style>
    <?php
});

/**
 * 3. Backend: Display data in the native "Users" list (Stable & Elegant)
 */

// A. Register new columns
add_filter( 'manage_users_columns', function( $columns ) {
    $columns['user_phone'] = 'Phone Number';
    $columns['user_code']  = 'Membership Code';
    return $columns;
});

// B. Populate column content
add_filter( 'manage_users_custom_column', function( $output, $column_id, $user_id ) {
    switch ( $column_id ) {
        case 'user_phone':
            return get_user_meta( $user_id, 'custom_phone', true ) ?: '-';
        case 'user_code':
            $code = get_user_meta( $user_id, 'membership_code', true );
            return $code ? '<strong style="color:#2271b1;">' . esc_html($code) . '</strong>' : '-';
    }
    return $output;
}, 10, 3 );

// C. Enable search for these columns (Allows searching by 8-digit code)
add_action( 'pre_user_query', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['s'] ) ) return;
    $query->query_where = str_replace(
        "user_nicename LIKE",
        "(user_nicename LIKE %1$s OR meta_value LIKE %1$s) AND user_nicename LIKE", 
        $query->query_where
    );
});

/**
 * 4. Member Center Menu Integration (Commented Out)
 */
/*
add_action('init', function() { 
    add_rewrite_endpoint('member-center', EP_PAGES); 
});

add_filter('woocommerce_account_menu_items', function($items) {
    $logout = $items['customer-logout'] ?? ''; 
    unset($items['customer-logout']);
    $items['member-center'] = 'Member Center';
    if($logout) $items['customer-logout'] = $logout;
    return $items;
});

add_action('woocommerce_account_member-center_endpoint', function() {
    echo '<h3>Membership</h3>' . do_shortcode('[pms-account]');
});
*/