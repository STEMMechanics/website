<?php

return [
    /*
     * The algorithm to use for hashing the challenge.
     * Should be SHA-256, SHA-384 or SHA-512.
     */
    'algorithm' => env('ALTCHA_ALGORITHM', 'SHA-256'),

    /*
     * The secret key to use for hashing the challenge.
     */
    'hmac_key' => env('ALTCHA_HMAC_KEY'),

    /*
     * The maximum value for the challenge.
     */
    'range_max' => env('ALTCHA_RANGE_MAX', \AltchaOrg\Altcha\ChallengeOptions::DEFAULT_MAX_NUMBER),

    /*
     * The expiration time for the challenge in seconds.
     */
    'expires' => env('ALTCHA_EXPIRES', 10),

    /*
     * The length of the salt to use for the challenge.
     */
    'salt_length' => env('ALTCHA_SALT_LENGTH', 12),

    /*
     * The route path to use for the challenge endpoint.
     */
    'route' => (static function () {
        $hmacKey = env('ALTCHA_HMAC_KEY');

        return is_string($hmacKey) && trim($hmacKey) !== ''
            ? '/altcha-challenge'
            : false;
    })(),

    /*
     * Keep this endpoint stateless to avoid rotating the main auth session
     * cookie from background widget requests.
     */
    'middleware' => ['throttle:10,1'],

    /*
     * Optional bypass value for tests.
     */
    'testing_bypass' => env('ALTCHA_TESTING_BYPASS'),
];
