<?php

return [

    'use_x_sendfile' => env('MEDIA_USE_X_SENDFILE', false),
    'use_x_accel' => env('MEDIA_USE_X_ACCEL', false),
    'x_accel_prefix' => env('MEDIA_X_ACCEL_PREFIX', '/protected/'),

];
