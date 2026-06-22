<?php

return [
    'driver' => env('POS_DRIVER', 'disabled'),
    'auto_complete' => env('POS_AUTO_COMPLETE', false),
    'terminal_id' => env('POS_TERMINAL_ID'),
    'webhook_secret' => env('POS_WEBHOOK_SECRET'),
    'fake_result' => env('POS_FAKE_RESULT', 'success'),
];
