<?php

namespace Tests\Unit;

use App\Services\WhatsApp\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsAppPhoneNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'local mobile' => ['082 123 4567', '27821234567'],
            'local with dashes' => ['082-123-4567', '27821234567'],
            'international plus' => ['+27 82 123 4567', '27821234567'],
            'international 00' => ['0027821234567', '27821234567'],
            'trunk zero after code' => ['+27 (0)82 123 4567', '27821234567'],
            'already wa_id' => ['27821234567', '27821234567'],
            'nine digit local without zero' => ['821234567', '27821234567'],
            'foreign number' => ['+44 7911 123456', '447911123456'],
            'empty' => ['', null],
            'too short' => ['12345', null],
            'letters only' => ['n/a', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalizes_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input, '27'));
    }
}
