<?php
/**
 * Plugin Name: Simple Membership Pro (Points & Checkout Edition)
 * Description: Membership levels, auto-downgrade, Reward Points management, and WooCommerce AJAX redemption with validation.
 * Version: 1.6
 * Author: Weiwei Chen
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. Plugin Activation: Register roles and scheduled tasks
 */
register_activation_hook(__FILE__, 'smp_plugin_activate');
function smp_plugin_activate() {
    add_role('basic_member', 'Basic Member', array('read' => true));
    add_role('gold_member', 'Gold Member', array('read' => true, 'upload_files' => true));

    if (!wp_next_scheduled('smp_check_expiry_cron')) {
        wp_schedule_event(time(), 'daily', 'smp_check_expiry_cron');
    }
}

/**
 * 2. Enqueue admin scripts for DatePicker
 */
add_action('admin_enqueue_scripts', 'smp_enqueue_admin_scripts');
function smp_enqueue_admin_scripts($hook) {
    if ('user-edit.php' != $hook && 'profile.php' != $hook) return;
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
}

/**
 * 3. User Registration: Auto-assign ID, role, and welcome points
 */
add_action('user_register', 'smp_auto_assign_info_and_points');
function smp_auto_assign_info_and_points($user_id) {
    $membership_id = 'ID-' . date('Y') . '-' . strtoupper(wp_generate_password(6, false));
    update_user_meta($user_id, 'membership_id', $membership_id);
    
    $user = new WP_User($user_id);
    $user->set_role('basic_member');

    update_user_meta($user_id, 'smp_points', 100);
}

/**
 * 4. Add "Points" column to the Users list table
 */
add_filter('manage_users_columns', 'smp_add_points_column');
function smp_add_points_column($columns) {
    $columns['smp_points'] = 'Points';
    return $columns;
}

add_filter('manage_users_custom_column', 'smp_show_points_column_content', 10, 3);
function smp_show_points_column_content($value, $column_name, $user_id) {
    if ('smp_points' == $column_name) {
        $points = get_user_meta($user_id, 'smp_points', true);
        return '<strong>' . ($points ? esc_html($points) : '0') . '</strong>';
    }
    return $value;
}

/**
 * 5. User Profile Page: Manage membership and points in backend
 */
add_action('show_user_profile', 'smp_show_extra_fields');
add_action('edit_user_profile', 'smp_show_extra_fields');
function smp_show_extra_fields($user) {
    $phone = get_user_meta($user->ID, 'smp_phone', true);
    $m_id = get_user_meta($user->ID, 'membership_id', true);
    $points = get_user_meta($user->ID, 'smp_points', true);
    $expiry_ts = get_user_meta($user->ID, 'membership_expiry_date', true);
    $expiry_date = $expiry_ts ? date('Y-m-d', $expiry_ts) : '';
    ?>
    <h3>Membership & Rewards</h3>
    <table class="form-table">
        <tr><th><label>Member ID</label></th><td><strong><?php echo esc_html($m_id); ?></strong></td></tr>
        <tr>
            <th><label for="smp_points">Reward Points</label></th>
            <td><input type="number" name="smp_points" id="smp_points" value="<?php echo esc_attr($points); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="smp_phone">Phone Number</label></th>
            <td><input type="text" name="smp_phone" id="smp_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="smp_expiry_picker">Expiry Date</label></th>
            <td><input type="text" name="smp_expiry_picker" id="smp_expiry_picker" value="<?php echo esc_attr($expiry_date); ?>" class="regular-text" placeholder="YYYY-MM-DD" /></td>
        </tr>
    </table>
    <script>jQuery(document).ready(function($) { $('#smp_expiry_picker').datepicker({ dateFormat: 'yy-mm-dd' }); });</script>
    <?php
}

add_action('personal_options_update', 'smp_save_extra_fields');
add_action('edit_user_profile_update', 'smp_save_extra_fields');
function smp_save_extra_fields($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'smp_phone', sanitize_text_field($_POST['smp_phone']));
        update_user_meta($user_id, 'smp_points', intval($_POST['smp_points']));
        if (!empty($_POST['smp_expiry_picker'])) {
            update_user_meta($user_id, 'membership_expiry_date', strtotime($_POST['smp_expiry_picker'] . ' 23:59:59'));
        }
    }
}

/**
 * 6. Expiry Logic: Auto-downgrade expired gold members
 */
add_action('smp_check_expiry_cron', 'smp_handle_downgrade');
function smp_handle_downgrade() {
    $expired_users = get_users(array(
        'role' => 'gold_member',
        'meta_query' => array(array('key' => 'membership_expiry_date', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC'))
    ));
    foreach ($expired_users as $user) {
        $user_obj = new WP_User($user->ID);
        $user_obj->set_role('basic_member');
        wp_mail($user->user_email, 'Membership Expired', "Your Gold Membership has expired.");
    }
}

/**
 * 7. Frontend Shortcode [user_card]
 */
add_shortcode('user_card', 'smp_user_card_shortcode');
function smp_user_card_shortcode() {
    if (!is_user_logged_in()) return '<p>Please log in.</p>';
    $user = wp_get_current_user();
    $points = get_user_meta($user->ID, 'smp_points', true) ?: '0';
    $m_id = get_user_meta($user->ID, 'membership_id', true) ?: 'N/A';
    $role = in_array('gold_member', (array)$user->roles) ? 'Gold' : 'Basic';
    return "<div style='border:1px solid #ddd; padding:20px; border-radius:8px; max-width:400px; background:#fff;'>
        <h3>MY MEMBERSHIP</h3>
        <p><strong>Points: <span style='color:#e67e22;'>{$points}</span></strong></p>
        <p><strong>Member ID:</strong> {$m_id}</p>
        <p><strong>Status:</strong> {$role}</p>
    </div>";
}

/**
 * 8. WooCommerce Checkout: AJAX UI for Points Redemption with Validation
 */
add_action('woocommerce_review_order_before_submit', 'smp_checkout_points_ui');
function smp_checkout_points_ui() {
    $user_id = get_current_user_id();
    if (!$user_id) return;
    $balance = intval(get_user_meta($user_id, 'smp_points', true));
    if ($balance > 0) {
        ?>
        <div id="smp_points_box" style="background:#f9f9f9; padding:15px; border:1px solid #ddd; margin:20px 0;">
            <h4 style="margin-top:0;">Membership Points Redemption</h4>
            <p>Available: <strong id="smp_max_points"><?php echo $balance; ?></strong> points.</p>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="number" id="smp_points_input" name="smp_points_input" placeholder="Amount" style="width:120px;">
                <button type="button" id="smp_apply_btn" class="button">Apply Discount</button>
            </div>
            <div id="smp_error_msg" style="color:#d63638; font-size:13px; margin-top:8px; display:none;">
                You cannot use more than your available points.
            </div>
            <small style="display:block; margin-top:8px;">* 100 points = €1 discount.</small>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var maxPoints = parseInt($('#smp_max_points').text());
            
            // Inline validation logic
            $('#smp_points_input').on('input', function() {
                var inputVal = parseInt($(this).val());
                if (inputVal > maxPoints) {
                    $('#smp_error_msg').show();
                    $('#smp_apply_btn').prop('disabled', true).css('opacity', '0.5');
                } else {
                    $('#smp_error_msg').hide();
                    $('#smp_apply_btn').prop('disabled', false).css('opacity', '1');
                }
            });

            // Trigger AJAX update
            $(document).on('click', '#smp_apply_btn', function(e) {
                e.preventDefault();
                $('body').trigger('update_checkout'); 
            });
        });
        </script>
        <?php
    }
}

/**
 * 9. Cart Calculation: Add discount fee
 */
add_action('woocommerce_cart_calculate_fees', 'smp_apply_checkout_fee', 25);
function smp_apply_checkout_fee($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $points_to_use = 0;
    if (!empty($_POST['post_data'])) {
        parse_str($_POST['post_data'], $post_data);
        $points_to_use = isset($post_data['smp_points_input']) ? intval($post_data['smp_points_input']) : 0;
    }
    if ($points_to_use > 0) {
        $balance = intval(get_user_meta(get_current_user_id(), 'smp_points', true));
        if ($points_to_use <= $balance) {
            $cart->add_fee('Points Discount', ($points_to_use / 100) * -1);
        }
    }
}

/**
 * 10. Final Deduction: Reduce user points after order completion
 */
add_action('woocommerce_checkout_update_order_meta', 'smp_deduct_points_on_order', 10, 2);
function smp_deduct_points_on_order($order_id, $data) {
    if (isset($_POST['smp_points_input']) && !empty($_POST['smp_points_input'])) {
        $used = intval($_POST['smp_points_input']);
        $uid = get_current_user_id();
        if ($uid && $used > 0) {
            $current = intval(get_user_meta($uid, 'smp_points', true));
            update_user_meta($uid, 'smp_points', max(0, $current - $used));
            $order = wc_get_order($order_id);
            $order->add_order_note("Redeemed $used points for this order.");
        }
    }
}