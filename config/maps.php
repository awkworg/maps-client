<?php

return [
    /*
    |--------------------------------------------------------------------------
    | The maps service
    |--------------------------------------------------------------------------
    |
    | Where the API is, and this app's key for it. The key is made with
    | `npm run client-key -w @awkw/maps -- <client>` in awkworg/maps, which also
    | gives the hash the service keeps. Keep it in the app's secrets.
    |
    */
    'url' => env('MAPS_URL', 'https://maps.awkw.org'),

    'key' => env('MAPS_KEY'),

    /*
    |--------------------------------------------------------------------------
    | The maps SDK
    |--------------------------------------------------------------------------
    |
    | Where maps.js is served, for Embed::html() to add its script tag. Without
    | it, the embed code is the <img> alone.
    |
    */
    'sdk_url' => env('MAPS_SDK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    |
    | Seconds before a call gives up, and how many more times one is tried when
    | the service says nothing was done (429, 503) or, for calls safe to
    | repeat, when it couldn't be reached.
    |
    */
    'timeout' => (int) env('MAPS_TIMEOUT', 10),

    'retries' => (int) env('MAPS_RETRIES', 2),
];
