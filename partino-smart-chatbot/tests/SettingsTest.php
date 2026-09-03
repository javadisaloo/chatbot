<?php
/**
 * Settings sanitization tests.
 *
 * @package Partino\Chatbot\Tests
 */

use PHPUnit\Framework\TestCase;
use Partino\Chatbot\Settings;

final class SettingsTest extends TestCase {

	public function test_defaults_have_all_sections(): void {
		$defaults = Settings::defaults();
		foreach ( array( 'general', 'appearance', 'chatbot', 'ai', 'conversation', 'lead', 'privacy', 'notifications', 'advanced' ) as $section ) {
			$this->assertArrayHasKey( $section, $defaults );
		}
	}

	public function test_invalid_color_is_ignored(): void {
		$clean = Settings::sanitize(
			array(
				'appearance' => array( 'primary_color' => 'javascript:alert(1)' ),
			)
		);
		$this->assertSame( Settings::defaults()['appearance']['primary_color'], $clean['appearance']['primary_color'] );
	}

	public function test_valid_color_is_saved(): void {
		$clean = Settings::sanitize(
			array(
				'appearance' => array( 'primary_color' => '#ff0000' ),
			)
		);
		$this->assertSame( '#ff0000', $clean['appearance']['primary_color'] );
	}

	public function test_open_delay_is_capped(): void {
		$clean = Settings::sanitize(
			array(
				'general' => array( 'open_delay' => 9999999 ),
			)
		);
		$this->assertLessThanOrEqual( 60000, $clean['general']['open_delay'] );
	}

	public function test_masked_api_key_is_not_overwritten(): void {
		update_option( Settings::OPTION, array( 'ai' => array( 'api_key' => 'sk-or-real-key-123456' ) ) );
		// Force cache refresh via reflection.
		$ref  = new ReflectionClass( Settings::class );
		$prop = $ref->getProperty( 'cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		$clean = Settings::sanitize(
			array(
				'ai' => array( 'api_key' => 'sk-or-**********3456' ),
			)
		);
		$this->assertSame( 'sk-or-real-key-123456', $clean['ai']['api_key'] );
	}

	public function test_empty_api_key_clears_it(): void {
		$clean = Settings::sanitize(
			array(
				'ai' => array( 'api_key' => '' ),
			)
		);
		$this->assertSame( '', $clean['ai']['api_key'] );
	}

	public function test_invalid_privacy_mode_falls_back(): void {
		$clean = Settings::sanitize(
			array(
				'privacy' => array( 'mode' => 'evil' ),
			)
		);
		$this->assertSame( 'standard', $clean['privacy']['mode'] );
	}

	public function test_export_masks_api_key(): void {
		update_option( Settings::OPTION, array( 'ai' => array( 'api_key' => 'sk-or-secret-key-abcd1234' ) ) );
		$ref  = new ReflectionClass( Settings::class );
		$prop = $ref->getProperty( 'cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		$export = Settings::export();
		$this->assertStringNotContainsString( 'secret-key', $export['ai']['api_key'] );
		$this->assertStringContainsString( '*', $export['ai']['api_key'] );
	}
}
