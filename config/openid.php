<?php

return [
    'passport' => [
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect',
            'profile' => 'Information about your profile',
            'email' => 'Information about your email address',
            'groups' => 'Information about your group memberships',
            'phone' => 'Information about your phone numbers',
            'address' => 'Information about your address',
        ],
    ],

    'custom_claim_sets' => [
        'groups' => [
            'groups',
        ],
    ],

    'repositories' => [
        'identity' => \OpenIDConnect\Repositories\IdentityRepository::class,
    ],

    'routes' => [
        'discovery' => true,
        'jwks' => true,
        'jwks_url' => '/oauth/jwks',
        'userinfo' => false,
    ],

    'discovery' => [
        'hide_scopes' => false,
    ],

    'signer' => \Lcobucci\JWT\Signer\Rsa\Sha256::class,

    'token_headers' => [],

    'use_microseconds' => true,

    'issuedBy' => 'laravel',

    'forceHttps' => true,
];
