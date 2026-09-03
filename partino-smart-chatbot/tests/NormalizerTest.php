<?php
/**
 * Normalizer unit tests.
 *
 * @package Partino\Chatbot\Tests
 */

use PHPUnit\Framework\TestCase;
use Partino\Chatbot\Normalizer;

final class NormalizerTest extends TestCase {

	public function test_persian_digits_converted(): void {
		$this->assertSame( '1388', Normalizer::digits( '۱۳۸۸' ) );
	}

	public function test_arabic_digits_converted(): void {
		$this->assertSame( '206', Normalizer::digits( '٢٠٦' ) );
	}

	public function test_arabic_letters_normalized(): void {
		$this->assertSame( 'کیا', Normalizer::text( 'كيا' ) );
	}

	public function test_whitespace_collapsed(): void {
		$this->assertSame( 'پژو 206', Normalizer::text( "  پژو   206 " ) );
	}

	public function test_number_words(): void {
		$this->assertSame( '206', Normalizer::number_words( 'دویست و شش' ) );
		$this->assertSame( '405', Normalizer::number_words( 'چهارصد و پنج' ) );
	}

	public function test_year_extraction_jalali(): void {
		$this->assertSame( '1388', Normalizer::extract_year( '1388' ) );
		$this->assertSame( '1390', Normalizer::extract_year( 'مدل ۱۳۹۰ هست' ) );
	}

	public function test_year_extraction_gregorian(): void {
		$this->assertSame( '2015', Normalizer::extract_year( '2015' ) );
	}

	public function test_year_two_digit_shorthand(): void {
		$this->assertSame( '1388', Normalizer::extract_year( '88' ) );
	}

	public function test_year_invalid_rejected(): void {
		$this->assertSame( '', Normalizer::extract_year( '123' ) );
		$this->assertSame( '', Normalizer::extract_year( '1200' ) );
		$this->assertSame( '', Normalizer::extract_year( 'سلام' ) );
	}

	public function test_score_exact_match(): void {
		$this->assertSame( 100, Normalizer::score( 'پژو 206', 'پژو 206' ) );
	}

	public function test_score_containment(): void {
		$this->assertGreaterThanOrEqual( 85, Normalizer::score( '206', 'پژو 206' ) );
	}

	public function test_score_empty_inputs(): void {
		$this->assertSame( 0, Normalizer::score( '', 'x' ) );
		$this->assertSame( 0, Normalizer::score( 'x', '' ) );
	}
}
