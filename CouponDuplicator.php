<?php
/**
 * Plugin Name:     CouponDuplicator
 * Description:     Quickly duplicate coupon with the same rules
 * Author:          Mateusz Zadorożny
 * Author URI:      https://zadorozny.rocks
 * Requires plugins: woocommerce
 * Text Domain:     CouponDuplicator
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         CouponDuplicator
 */

class CouponDuplicator
{
    public function __construct()
    {
        add_filter('post_row_actions', [$this, 'add_duplicate_coupon_button'], 10, 2);
        add_action('admin_action_duplicate_coupon', [$this, 'duplicate_coupon']);
    }

    public function add_duplicate_coupon_button($actions, $post)
    {
        if ($post->post_type == 'shop_coupon') {
            $duplicate_url = wp_nonce_url('admin.php?action=duplicate_coupon&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce');
            $actions['duplicate'] = '<a href="' . $duplicate_url . '" title="Duplicate this coupon" rel="permalink">Duplicate with same rules</a>';
        }
        return $actions;
    }

    public function duplicate_coupon()
    {
        if (!isset($_GET['post']) || !isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], basename(__FILE__))) {
            error_log('Nonce verification failed or post ID not set');
            return;
        }

        $post_id = absint($_GET['post']);
        $post = get_post($post_id);

        if ($post && $post->post_type == 'shop_coupon') {
            error_log('Duplicating coupon with ID: ' . $post_id);

            $new_code = $this->generate_random_code();
            $new_post_id = wp_insert_post(
                array(
                    'post_title' => $new_code,
                    'post_content' => $post->post_content,
                    'post_status' => 'draft',
                    'post_type' => 'shop_coupon',
                    'post_name' => $new_code // Ustawienie sluga na nowy kod
                )
            );

            if ($new_post_id) {
                error_log('New coupon created with ID: ' . $new_post_id);

                $coupon_meta_keys = array(
                    'discount_type',
                    'coupon_amount',
                    'individual_use',
                    'product_ids',
                    'exclude_product_ids',
                    'usage_limit',
                    'usage_limit_per_user',
                    'limit_usage_to_x_items',
                    'free_shipping',
                    'date_expires',
                    'minimum_amount',
                    'maximum_amount',
                    'customer_email'
                );

                foreach ($coupon_meta_keys as $meta_key) {
                    $meta_value = get_post_meta($post_id, $meta_key, true);
                    if ($meta_key === 'date_expires' && $meta_value) {
                        // date_expires jest już w formacie timestamp, więc nie trzeba go konwertować
                        error_log("Original expiry date timestamp: " . $meta_value);
                    }
                    update_post_meta($new_post_id, $meta_key, $meta_value);
                    error_log("Meta key $meta_key copied with value: " . print_r($meta_value, true));
                }

                update_post_meta($new_post_id, 'coupon_code', $new_code);
                error_log('New coupon code generated: ' . $new_code);

                // Odśwież listę kuponów
                wp_redirect(admin_url('edit.php?post_type=shop_coupon'));
                exit;
            } else {
                error_log('Failed to create new coupon post');
            }
        } else {
            error_log('Invalid post or post type');
        }
    }

    private function generate_random_code($length = 8)
    {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }
}

new CouponDuplicator();