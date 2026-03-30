<?php

namespace Tests;

use App\Support\PassportKeyManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static bool $passportKeysEnsured = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeysExist();
    }

    private function ensurePassportKeysExist(): void
    {
        if (self::$passportKeysEnsured) {
            return;
        }

        app(PassportKeyManager::class)->ensureKeysExist();

        self::$passportKeysEnsured = true;
    }
}
