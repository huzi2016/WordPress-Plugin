<?php
/**
 * Plugin Name: My Custom WC Account Ultimate V2.0
 * Description: 优雅方案：修复前端按钮布局，并将会员码与电话显示在原生用户列表。
 * Version: 2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * 1. 核心数据逻辑
 */
function wc_ensure_member_data( $user_id ) {
    if ( ! get_user_meta( $user_id, 'membership_code', true ) ) {
        $code = strtoupper( wp_generate_password( 8, false ) );
        update_user_meta( $user_id, 'membership_code', $code );
    }
}
add_action( 'woocommerce_created_customer', 'wc_ensure_member_data' );
add_action( 'woocommerce_save_account_details', function($user_id){
    if(isset($_POST['custom_phone'])) update_user_meta($user_id, 'custom_phone', sanitize_text_field($_POST['custom_phone']));
    wc_ensure_member_data($user_id);
});

/**
 * 2. 前端：物理置底与姓名并排 (完全保留已验证成功的方案)
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
            <label for="custom_phone">custom_phone (Phone)</label>
            <input type="text" name="custom_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'custom_phone', true)); ?>" />
        </p>
    </div>

    <style>
        /* 强制表单块状化 */
        .woocommerce-EditAccountForm { display: block !important; }
        /* 姓名并排精准修复 */
        .woocommerce-EditAccountForm .form-row-first { width: 47% !important; float: left !important; margin-right: 3% !important; clear: none !important; }
        .woocommerce-EditAccountForm .form-row-last { width: 50% !important; float: left !important; clear: none !important; }
        /* 强制按钮置底隔离 */
        .woocommerce-EditAccountForm button.button.save_account_details { 
            display: block !important; clear: both !important; float: none !important; margin: 50px 0 20px 0 !important; position: relative !important;
        }
    </style>
    <?php
});

/**
 * 3. 后台：在原生“用户 (Users)”列表显示数据 (优雅且稳定)
 */

// A. 注册新列
add_filter( 'manage_users_columns', function( $columns ) {
    $columns['user_phone'] = 'custom_phone';
    $columns['user_code']  = 'Membership Code';
    return $columns;
});

// B. 填充内容
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

// C. 让这些列支持搜索 (可选：这样您就能直接搜索 8 位会员码了)
add_action( 'pre_user_query', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['s'] ) ) return;
    $query->query_where = str_replace(
        "user_nicename LIKE",
        "(user_nicename LIKE %1$s OR meta_value LIKE %1$s) AND user_nicename LIKE", 
        $query->query_where
    );
});

/**
 * 4. 会员中心菜单集成
 */
// add_action('init', function() { add_rewrite_endpoint('member-center', EP_PAGES); });
// add_filter('woocommerce_account_menu_items', function($items) {
//     $logout = $items['customer-logout'] ?? ''; unset($items['customer-logout']);
//     $items['member-center'] = 'Member Center';
//     if($logout) $items['customer-logout'] = $logout;
//     return $items;
// });
// add_action('woocommerce_account_member-center_endpoint', function() {
//     echo '<h3>Membership</h3>' . do_shortcode('[pms-account]');
// });