<?php

namespace Tests\Unit;

use App\Support\CsvPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvPhoneNumberTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function test_formats_phone_numbers_without_losing_country_codes(?string $input, string $expected): void
    {
        $this->assertSame($expected, CsvPhoneNumber::format($input));
    }

    public static function phoneNumbers(): array
    {
        return [
            'mobile' => ['0438129753', '0438 129 753'],
            'already formatted' => ['0421 632 634', '0421 632 634'],
            'punctuation' => ['0438-129-753', '0438 129 753'],
            'landline' => ['0212345678', '02 1234 5678'],
            'landline with brackets' => ['(07) 3123-4567', '07 3123 4567'],
            'international mobile' => ['+61438129753', '+61 438 129 753'],
            'international landline' => ['+61212345678', '+61 2 1234 5678'],
            'optional trunk prefix' => ['+61 (0)438 129 753', '+61 438 129 753'],
            'bare Australian country code' => ['61438129753', '+61 438 129 753'],
            'international dialling prefix' => ['0061438129753', '+61 438 129 753'],
            'Australian dialling prefix' => ['0011442079460958', '+44 20 7946 0958'],
            'UK' => ['+442079460958', '+44 20 7946 0958'],
            'US' => ['+12025550123', '+1 202 555 0123'],
            'NZ' => ['+64211234567', '+64 21 123 4567'],
            'significant Italian zero' => ['+390669821', '+39 06 69821'],
            'empty' => ['', ''],
            'null' => [null, ''],
            'short number' => ['123', '123'],
            'unknown country code' => ['+999123456789', '+999123456789'],
            'extension' => ['02 1234 5678 ext 12', '02 1234 5678 ext 12'],
            'notes' => ['Call after 4pm', 'Call after 4pm'],
        ];
    }
}
