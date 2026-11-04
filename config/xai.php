<?php

declare(strict_types=1);

return [

    // Passed as the SpaceXSI apiKey. When this is empty, the client reads XAI_API_KEY.
    'api_key' => env('XAI_API_KEY'),

    // Null leaves the PHP client's own default in place.
    'base_url' => null,

    // Null leaves the PHP client's default. 0, a negative value, or anything past 24 hours becomes 24 hours.
    'timeout' => null,

    // Null leaves the PHP client's default. 0, a negative value, or anything past 24 hours becomes 24 hours.
    'idle_timeout' => null,

    'max_response_body_bytes' => null,

    'max_retries' => null,

    // When true, a dropped connection is retried before the first response byte, including a stream that returns 200 and then drops before the event stream starts.
    'retry_before_output' => null,

    'default_headers' => [],

];
