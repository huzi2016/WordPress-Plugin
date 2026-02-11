📦 WooCommerce Membership & Rewards Suite
A professional-grade suite of WordPress plugins designed to manage membership lifecycles, unique identification, and a seamless reward points redemption system at checkout.

1. Simple Membership Pro (Points & Checkout Edition)
Version: 1.6

Core Purpose: Handles the reward points economy and automated membership tiering.

🚀 Features

Point Economy: Implements a "100 Points = €1" conversion rate.

AJAX Checkout Redemption: Adds a custom redemption box on the WooCommerce checkout page. Users can apply points to their total without a page refresh.

Real-time Validation: JavaScript-powered input validation prevents users from entering more points than their current balance.

Automated Lifecycle:

Auto-Registration: Generates a unique Member ID and grants 100 welcome points to new users.

Auto-Downgrade: A daily Cron job automatically reverts expired Gold Members to Basic status.

Admin Visibility: Adds a "Points" column to the native WordPress User list for easy oversight.

Frontend Display: Use the [user_card] shortcode to show users their points, ID, and status in a styled box.

2. My Custom WC Account Ultimate
Version: 2.1

Core Purpose: Enhances the WooCommerce "My Account" area and administrative data search.

🚀 Features

Membership ID System: Generates a permanent, 8-character alphanumeric Membership Code (separate from the Points Member ID) for tracking.

UI/UX Layout Fixes:

Forces "First Name" and "Last Name" to remain side-by-side on all screen sizes.

Fixes "Floating Button" bugs by ensuring the "Save Changes" button stays pinned to the bottom.

Global Admin Search: Modifies the WordPress User Search to allow administrators to find customers by their Membership Code or Phone Number.

Phone Integration: Captures and displays a custom phone number field within the Account Details and the Admin User list.

🛠 Installation & Setup
Upload: Place both plugin folders into your /wp-content/plugins/ directory.

Activate: Activate both from the WordPress Plugins dashboard.

Shortcodes:

Place [user_card] on your "My Account" or "Member Dashboard" page to show points.

(Optional) Use [pms-account] if integrating with Paid Member Subscriptions.

📝 Technical Notes
Database Keys:

Points: smp_points

Membership Code: membership_code

Phone: custom_phone / smp_phone

Dependencies: Requires WooCommerce to be installed and active.

CSS Isolation: Both plugins use scoped CSS to prevent styling conflicts with your WordPress theme.

Author: Weiwei Chen

Status: Git Baseline Version 2024.1
