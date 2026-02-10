<?php
/**
 * Plugin Name: Simple Membership Pro (Points Edition)
 * Description: Membership levels, auto-downgrade, date picker, email notifications, and Reward Points.
 * Version: 1.3
 * Author: Weiwei Chen
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. 插件激活：注册角色与定时任务
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
 * 2. 引入后台脚本（日历控件）
 */
add_action('admin_enqueue_scripts', 'smp_enqueue_admin_scripts');
function smp_enqueue_admin_scripts($hook) {
    if ('user-edit.php' != $hook && 'profile.php' != $hook) return;
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
}

/**
 * 3. 核心：用户注册时自动生成信息 + 赠送首次积分
 */
add_action('user_register', 'smp_auto_assign_info_and_points');
function smp_auto_assign_info_and_points($user_id) {
    // A. 生成会员号
    $membership_id = 'ID-' . date('Y') . '-' . strtoupper(wp_generate_password(6, false));
    update_user_meta($user_id, 'membership_id', $membership_id);
    
    // B. 分配默认角色
    $user = new WP_User($user_id);
    $user->set_role('basic_member');

    // C. 赠送首次注册积分 (例如送 100 积分)
    $initial_points = 100; 
    update_user_meta($user_id, 'smp_points', $initial_points);
}

/**
 * 4. 在“所有用户”列表添加“积分”列
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
 * 5. 后台用户编辑页面：添加积分管理
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
        <tr>
            <th><label>Member ID</label></th>
            <td><strong><?php echo esc_html($m_id); ?></strong></td>
        </tr>
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
            <td>
                <input type="text" name="smp_expiry_picker" id="smp_expiry_picker" value="<?php echo esc_attr($expiry_date); ?>" class="regular-text" placeholder="YYYY-MM-DD" />
            </td>
        </tr>
    </table>
    <script>
        jQuery(document).ready(function($) {
            $('#smp_expiry_picker').datepicker({ dateFormat: 'yy-mm-dd' });
        });
    </script>
    <?php
}

add_action('personal_options_update', 'smp_save_extra_fields');
add_action('edit_user_profile_update', 'smp_save_extra_fields');
function smp_save_extra_fields($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'smp_phone', sanitize_text_field($_POST['smp_phone']));
        update_user_meta($user_id, 'smp_points', intval($_POST['smp_points'])); // 保存积分
        
        if (!empty($_POST['smp_expiry_picker'])) {
            $timestamp = strtotime($_POST['smp_expiry_picker'] . ' 23:59:59');
            update_user_meta($user_id, 'membership_expiry_date', $timestamp);
        }
    }
}

/**
 * 6. 到期降级逻辑 (保持不变)
 */
add_action('smp_check_expiry_cron', 'smp_handle_downgrade');
function smp_handle_downgrade() {
    $now = time();
    $expired_users = get_users(array(
        'role' => 'gold_member',
        'meta_query' => array(array('key' => 'membership_expiry_date', 'value' => $now, 'compare' => '<', 'type' => 'NUMERIC'))
    ));
    foreach ($expired_users as $user) {
        $user_obj = new WP_User($user->ID);
        $user_obj->set_role('basic_member');
        wp_mail($user->user_email, 'Membership Expired', "Your Gold Membership has expired.");
    }
}

/**
 * 7. 前端展示卡片 [user_card] (增加积分显示)
 */
add_shortcode('user_card', 'smp_user_card_shortcode');
function smp_user_card_shortcode() {
    if (!is_user_logged_in()) return '<p>Please log in.</p>';
    $user = wp_get_current_user();
    $points = get_user_meta($user->ID, 'smp_points', true) ?: '0';
    $m_id = get_user_meta($user->ID, 'membership_id', true) ?: 'N/A';
    
    return "
    <div style='border:1px solid #ddd; padding:20px; border-radius:8px; max-width:400px; background:#fff; font-family: sans-serif;'>
        <h3 style='margin-top:0;'>MY MEMBERSHIP</h3>
        <p><strong>Points: <span style='color:#e67e22;'>{$points}</span></strong></p>
        <p><strong>Member ID:</strong> {$m_id}</p>
        <p><strong>Status:</strong> " . (in_array('gold_member', (array)$user->roles) ? 'Gold' : 'Basic') . "</p>
    </div>";
}