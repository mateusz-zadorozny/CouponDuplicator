<?php
// tests/phpunit/test-coupon-duplicator.php

class CouponDuplicatorTest extends WP_UnitTestCase
{
	public function setUp()
	{
		parent::setUp();
		$this->duplicator = new CouponDuplicator();
	}

	public function test_add_duplicate_coupon_button()
	{
		$post = $this->factory->post->create_and_get(array('post_type' => 'shop_coupon'));
		$actions = array();
		$actions = $this->duplicator->add_duplicate_coupon_button($actions, $post);

		$this->assertArrayHasKey('duplicate', $actions);
		$this->assertStringContainsString('Duplicate with same rules', $actions['duplicate']);
	}

	public function test_generate_random_code()
	{
		$code = $this->duplicator->generate_random_code();
		$this->assertEquals(8, strlen($code));
		$this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
	}

	public function test_duplicate_coupon()
	{
		// Tworzenie przykładowego kuponu
		$post_id = $this->factory->post->create(array('post_type' => 'shop_coupon', 'post_title' => 'TESTCODE'));
		update_post_meta($post_id, 'discount_type', 'percent');
		update_post_meta($post_id, 'coupon_amount', '35');
		update_post_meta($post_id, 'date_expires', strtotime('+1 week'));

		// Symulowanie żądania duplikacji
		$_GET['post'] = $post_id;
		$_GET['duplicate_nonce'] = wp_create_nonce(basename(__FILE__));

		$this->duplicator->duplicate_coupon();

		// Sprawdzenie, czy nowy kupon został utworzony
		$args = array(
			'post_type' => 'shop_coupon',
			'post_status' => 'draft',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'order' => 'DESC'
		);
		$coupons = get_posts($args);
		$this->assertCount(2, $coupons);

		// Sprawdzenie, czy meta dane zostały poprawnie skopiowane
		$new_coupon = $coupons[0]; // Najnowszy kupon
		$this->assertNotEquals($post_id, $new_coupon->ID);
		$this->assertEquals('percent', get_post_meta($new_coupon->ID, 'discount_type', true));
		$this->assertEquals('35', get_post_meta($new_coupon->ID, 'coupon_amount', true));
		$this->assertEquals(strtotime('+1 week'), get_post_meta($new_coupon->ID, 'date_expires', true));
	}
}