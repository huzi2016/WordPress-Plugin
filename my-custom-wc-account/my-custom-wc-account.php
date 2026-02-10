<?php
/**
 * Plugin Name: My Custom WC Account Ultimate V1.7.2
 * Description: 彻底解决按钮置底问题，确保姓和名完美并齐。
 * Version: 1.7.2
 * Author: Weiwei Chen
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. 注册端点 & 菜单排序
 */
add_action('init', function() {
    add_rewrite_endpoint('member-center', EP_PAGES);
});

add_filter('woocommerce_account_menu_items', function($items) {
    $logout = isset($items['customer-logout']) ? $items['customer-logout'] : '';
    if ($logout) unset($items['customer-logout']);
    $items['member-center'] = 'Member Center';
    if ($logout) $items['customer-logout'] = $logout;
    return $items;
}, 999);

/**
 * 2. 替换 Member Center 内容为 PMS 信息
 */
add_action('woocommerce_account_member-center_endpoint', function() {
    echo '<h3>Membership Details</h3>';
    echo do_shortcode('[pms-account]'); 
});

/**
 * 3. 账户详情页布局修复
 */
add_action( 'woocommerce_edit_account_form', function() {
    $user = wp_get_current_user();
    ?>
    <div class="custom-phone-field-wrapper">
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="custom_phone">custom_phone (Phone)</label>
            <input type="text" class="input-text" name="custom_phone" id="custom_phone" value="<?php echo esc_attr( get_user_meta( $user->ID, 'custom_phone', true ) ); ?>" />
        </p>
    </div>
    
    <style>
        /* 清除之前的 Flex 干扰，回归标准流布局 */
        .woocommerce-EditAccountForm { 
            display: block !important; 
        }

        /* 修复姓名格子并排对齐 */
        .woocommerce-EditAccountForm .form-row-first { 
            width: 47% !important; 
            float: left !important;
            margin-right: 3% !important;
            clear: none !important;
        }
        .woocommerce-EditAccountForm .form-row-last { 
            width: 50% !important; 
            float: left !important;
            clear: none !important;
        }

        /* 确保后续字段清除浮动，占满全宽 */
        .woocommerce-EditAccountForm .form-row-wide,
        .custom-phone-field-wrapper { 
            clear: both !important;
            width: 100% !important; 
            display: block !important;
        }

        /* 密码修改框 */
        .woocommerce-EditAccountForm fieldset { 
            clear: both !important;
            margin-top: 30px !important;
            display: block !important;
        }

        /* 核心修复：强制按钮另起一行并出现在最下方 */
        .woocommerce-EditAccountForm .woocommerce-Button,
        .woocommerce-EditAccountForm button[name="save_account_details"] { 
            display: block !important;
            clear: both !important; /* 彻底切断上方浮动干扰 */
            margin-top: 40px !important;
            position: relative !important;
            top: 0 !important;
            left: 0 !important;
        }

        /* 移动端适配 */
        @media (max-width: 768px) {
            .woocommerce-EditAccountForm .form-row-first, 
            .woocommerce-EditAccountForm .form-row-last { width: 100% !important; float: none !important; margin-right: 0 !important; }
        }
    </style>
    <?php
});

/**
 * 4. 统一保存逻辑及后台显示
 */
add_action( 'woocommerce_save_account_details', 'custom_save_phone_final' );
add_action( 'personal_options_update', 'custom_save_phone_final' );
add_action( 'edit_user_profile_update', 'custom_save_phone_final' );
add_action( 'woocommerce_created_customer', 'custom_save_phone_final' );
function custom_save_phone_final( $user_id ) {
    if ( isset( $_POST['custom_phone'] ) ) update_user_meta( $user_id, 'custom_phone', sanitize_text_field( $_POST['custom_phone'] ) );
}

add_filter( 'manage_users_columns', function($cols){ $cols['user_phone']='custom_phone'; return $cols; });
add_filter( 'manage_users_custom_column', function($v,$n,$id){
    return ($n==='user_phone') ? (get_user_meta($id,'custom_phone',true) ?: '-') : $v;
}, 10, 3);