<?php
/**
 * Plugin Name: My Custom WC Account Ultimate V1.7.8
 * Description: 彻底修复 PMS 后台电话显示（解决加载中）、物理重置前端按钮位置、修复姓名并齐。
 * Version: 1.7.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. 基础配置与 PMS 集成
 */
add_action('init', function() { add_rewrite_endpoint('member-center', EP_PAGES); });
add_filter('woocommerce_account_menu_items', function($items) {
    $logout = $items['customer-logout'] ?? '';
    unset($items['customer-logout']);
    $items['member-center'] = 'Member Center';
    if ($logout) $items['customer-logout'] = $logout;
    return $items;
}, 999);
add_action('woocommerce_account_member-center_endpoint', function() {
    echo '<h3>Membership Details</h3>' . do_shortcode('[pms-account]');
});

/**
 * 2. 前端：物理重置布局（解决按钮与 First Name 重叠）
 */
add_action( 'woocommerce_edit_account_form', function() {
    $user = wp_get_current_user();
    // 电话字段
    echo '<div class="custom-phone-row" style="clear:both; width:100%; margin-bottom:20px;">
            <label for="custom_phone" style="display:block;">联系电话 (Phone)</label>
            <input type="text" class="input-text" name="custom_phone" value="'.esc_attr(get_user_meta($user->ID, 'custom_phone', true)).'" style="width:100%;" />
          </div>';
    ?>
    <style>
        /* 强制表单块状化，杜绝一切 Float 或 Flex 引起的重叠 */
        .woocommerce-EditAccountForm { display: block !important; }
        
        /* 姓名并齐精准修复 */
        .woocommerce-EditAccountForm .form-row-first { width: 47% !important; float: left !important; margin-right: 3% !important; clear: none !important; }
        .woocommerce-EditAccountForm .form-row-last { width: 50% !important; float: left !important; clear: none !important; }
        
        /* 其它字段清除浮动，确保垂直排列 */
        .woocommerce-EditAccountForm .form-row-wide, .custom-phone-row { clear: both !important; display: block !important; width: 100% !important; }
        
        /* 按钮重压置底：彻底切断与上方字段的任何关联 */
        .woocommerce-EditAccountForm button.button.save_account_details { 
            display: block !important; 
            margin: 50px 0 20px 0 !important;
            clear: both !important;
            float: none !important;
            position: relative !important;
        }
    </style>
    <?php
});

/**
 * 3. 后台：解决 PMS 免费版电话显示“加载中”问题
 */

// A. 注入 JS 脚本处理表格渲染
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'paid-member-subscriptions_page_pms-members-page' ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // 注入表头列
                if ($('.wp-list-table thead tr th.column-email').length) {
                    $('.wp-list-table thead tr th.column-email').after('<th class="manage-column">custom_phone</th>');
                    $('.wp-list-table tfoot tr th.column-email').after('<th class="manage-column">custom_phone</th>');
                }
                
                // 遍历行并填充数据
                $('.wp-list-table tbody tr').each(function() {
                    var $row = $(this);
                    // 免费版 PMS 表格通常在 ID 列显示 User ID
                    var userId = $row.find('.column-user_id').text().trim(); 
                    if (!userId) {
                        // 如果 ID 列拿不到，尝试从 Checkbox 的 value 拿
                        userId = $row.find('input[name="members[]"]').val();
                    }

                    if (userId) {
                        var $targetCell = $('<td class="column-phone">Loading...</td>');
                        $row.find('.column-email').after($targetCell);
                        
                        // 发起 AJAX 请求获取真实电话
                        $.post(ajaxurl, {
                            action: 'get_user_phone_by_ajax',
                            user_id: userId
                        }, function(response) {
                            $targetCell.html(response.data || '-');
                        });
                    } else {
                        $row.find('.column-email').after('<td>-</td>');
                    }
                });
            });
        </script>
        <?php
    }
});

// B. 处理 AJAX 请求以填充电话数据
add_action( 'wp_ajax_get_user_phone_by_ajax', function() {
    $user_id = intval($_POST['user_id']);
    // 先尝试通过 PMS 的 Member ID 获取 User ID
    $real_user_id = get_post_meta($user_id, 'pms_member_user_id', true) ?: $user_id;
    $phone = get_user_meta($real_user_id, 'custom_phone', true);
    wp_send_json_success($phone ?: '-');
});

/**
 * 4. 保存逻辑与常规用户列表
 */
add_action( 'woocommerce_save_account_details', 'custom_ultimate_save_logic' );
add_action( 'personal_options_update', 'custom_ultimate_save_logic' );
function custom_ultimate_save_logic( $user_id ) {
    if ( isset( $_POST['custom_phone'] ) ) update_user_meta( $user_id, 'custom_phone', sanitize_text_field( $_POST['custom_phone'] ) );
}

add_filter( 'manage_users_columns', function($cols){ $cols['u_phone']='custom_phone'; return $cols; });
add_filter( 'manage_users_custom_column', function($v,$n,$id){
    return ($n==='u_phone') ? (get_user_meta($id,'custom_phone',true) ?: '-') : $v;
}, 10, 3);