<?php

declare(strict_types=1);

return [

    // Passed as the SpaceXAI apiKey. When this is empty, the client reads XAI_API_KEY.
    'api_key' => env('XAI_API_KEY'),

    // Null leaves the PHP client's own default in place.
    'base_url' => null,

    'timeout' => null,

    'idle_timeout' => null,

    'max_response_body_bytes' => null,

    'max_retries' => null,

    'retry_before_output' => null,

    'default_headers' => [],

];
