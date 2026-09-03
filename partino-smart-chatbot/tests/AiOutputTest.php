<?php
/**
 * AI structured-output validation tests (OpenRouter responses are mocked
 * as raw strings — no network access needed).
 *
 * @package Partino\Chatbot\Tests
 */

use PHPUnit\Framework\TestCase;
use Partino\Chatbot\AI;

final class AiOutputTest extends TestCase {

	public function test_valid_json_is_accepted(): void {
		$result = AI::validate_output( '{"intent":"vehicle_model","vehicle_brand":"Peugeot","vehicle_model":"206","confidence":0.98}' );
		$this->assertNotNull( $result );
		$this->assertSame( 'vehicle_model', $result['intent'] );
		$this->assertSame( 'Peugeot', $result['vehicle_brand'] );
		$this->assertSame( '206', $result['vehicle_model'] );
	}

	public function test_code_fences_are_stripped(): void {
		$result = AI::validate_output( "```json\n{\"intent\":\"confirm\",\"confidence\":1}\n```" );
		$this->assertNotNull( $result );
		$this->assertSame( 'confirm', $result['intent'] );
	}

	public function test_json_extracted_from_prose(): void {
		$result = AI::validate_output( 'Result: {"intent":"part_request","part":"لنت ترمز","confidence":0.9} done.' );
		$this->assertNotNull( $result );
		$this->assertSame( 'لنت ترمز', $result['part'] );
	}

	public function test_unknown_intent_is_rejected(): void {
		$this->assertNull( AI::validate_output( '{"intent":"drop_table","confidence":1}' ) );
	}

	public function test_broken_json_is_rejected(): void {
		$this->assertNull( AI::validate_output( '{intent: nope' ) );
		$this->assertNull( AI::validate_output( '' ) );
		$this->assertNull( AI::validate_output( 'plain text answer' ) );
	}

	public function test_confidence_is_clamped(): void {
		$result = AI::validate_output( '{"intent":"confirm","confidence":99}' );
		$this->assertSame( 1.0, $result['confidence'] );

		$result = AI::validate_output( '{"intent":"confirm","confidence":-5}' );
		$this->assertSame( 0.0, $result['confidence'] );
	}

	public function test_html_is_stripped_from_fields(): void {
		$result = AI::validate_output( '{"intent":"vehicle_model","vehicle_model":"<script>x</script>206","confidence":0.9}' );
		$this->assertStringNotContainsString( '<script>', (string) $result['vehicle_model'] );
	}
}
