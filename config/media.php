<?php

return [

    'use_x_sendfile' => env('MEDIA_USE_X_SENDFILE', false),
    'use_x_accel' => env('MEDIA_USE_X_ACCEL', false),
    'x_accel_prefix' => env('MEDIA_X_ACCEL_PREFIX', '/protected/'),
    'chunk_upload_ttl_minutes' => (int) env('MEDIA_CHUNK_UPLOAD_TTL_MINUTES', 120),

];
