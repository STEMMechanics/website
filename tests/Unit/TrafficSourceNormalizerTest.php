<?php

namespace Tests\Unit;

use App\Services\TrafficSourceNormalizer;
use PHPUnit\Framework\TestCase;

class TrafficSourceNormalizerTest extends TestCase
{
    public function test_it_normalizes_all_domain_variants_by_organisation_label(): void
    {
        $normalizer = new TrafficSourceNormalizer;

        foreach ([
            'google.com',
            'www.google.com.au',
            'calendar.google.com',
            'com.google.android.googlequicksearchbox',
        ] as $source) {
            $this->assertSame('Google', $normalizer->normalize($source));
        }

        $this->assertSame('Facebook', $normalizer->normalize('m.facebook.com'));
        $this->assertSame('Bing', $normalizer->normalize('www.bing.com.au'));
        $this->assertSame('Mail Tester', $normalizer->normalize('www.mail-tester.com'));
        $this->assertSame('Cairnsminecraft', $normalizer->normalize('shop.cairnsminecraft.com.au'));
        $this->assertSame('Direct / unknown', $normalizer->normalize('Direct / unknown'));
    }
}
