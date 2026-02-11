<?php
/*
Plugin Name: Medical Device Points Discount (SMP Integrated)
Description: Integrated with Simple Membership Pro. Uses 'smp_points' for checkout discount. 100 points = €1.
Version: 1.4
Author: Weiwei Chen
*/

if (!defined('ABSPATH')) exit;

/**
 * 1. Display points redemption box on the checkout page.
 * Hooked near the "Place Order" button for better visibility.
 */
add_action('woocommerce_review_order_before_submit', 'smp_integrated_render_points_box');
function smp_integrated_render_points_box() {
    $user_id = get_current_user_id();
    if (!$user_id) return;

    // Fetch points using your SMP plugin's specific meta key
    $points_balance = get_user_meta($user_id, 'smp_points', true);
    $points_balance = intval($points_balance); 

    if ($points_balance > 0) {
        ?>
        <div id="smp_points_redeem_wrapper" style="background:#fcfcfc; padding:20px; border:1px solid #d1d1d1; margin-bottom:20px; border-radius:4px;">
            <h4 style="margin:0 0 10px 0; color:#333;">Membership Points Redemption</h4>
            <p style="margin:0 0 10px 0;">You have <strong><?php echo $points_balance; ?></strong> points available.</p>
            <div style="display:flex; gap:10px;">
                <input type="number" id="smp_points_to_redeem" name="smp_points_to_redeem" placeholder="Amount" style="width:120px;">
                <button type="submit" class="button" name="apply_smp_points" value="apply">Apply Discount</button>
            </div>
            <small style="display:block; margin-top:10px; color:#666;">* 100 points = €1 discount</small>
        </div>
        <?php
    }
}

/**
 * 2. Calculate and apply the points discount to the total.
 */
add_action('woocommerce_cart_calculate_fees', 'smp_integrated_apply_discount', 25);
function smp_integrated_apply_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    if (isset($_POST['smp_points_to_redeem']) && !empty($_POST['smp_points_to_redeem'])) {
        $requested_points = intval($_POST['smp_points_to_redeem']);
        $user_id = get_current_user_id();
        
        // Double check balance from SMP field
        $actual_balance = intval(get_user_meta($user_id, 'smp_points', true));

        if ($requested_points > $actual_balance) {
            wc_add_notice('Error: You do not have enough points.', 'error');
            return;
        }

        if ($requested_points > 0) {
            // Conversion: 100 points = €1
            $discount_value = ($requested_points / 100) * -1;
            $cart->add_fee('Member Points Discount', $discount_value);
        }
    }
}

/**
 * 3. Deduct used points from SMP balance after successful order.
 */
add_action('woocommerce_checkout_update_order_meta', 'smp_integrated_deduct_points', 10, 2);
function smp_integrated_deduct_points($order_id, $data) {
    if (isset($_POST['smp_points_to_redeem']) && !empty($_POST['smp_points_to_redeem'])) {
        $points_used = intval($_POST['smp_points_to_redeem']);
        $user_id = get_current_user_id();
        
        if ($user_id && $points_used > 0) {
            $current_balance = intval(get_user_meta($user_id, 'smp_points', true));
            $new_balance = max(0, $current_balance - $points_used);
            
            // Sync back to your SMP meta key
            update_user_meta($user_id, 'smp_points', $new_balance);
            
            // Record in order notes for audit
            $order = wc_get_order($order_id);
            $order->add_order_note(sprintf('Points Redeemed: %d points from Simple Membership balance.', $points_used));
        }
    }
}