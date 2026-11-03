<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI driver
    |--------------------------------------------------------------------------
    |
    | 'mock' uses a deterministic filter-based recommender (development default).
    | 'openai' calls the OpenAI API through the OpenAiService.
    |
    */

    'driver' => env('AI_PROVIDER', env('AI_DRIVER', 'mock')),

    'model' => env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4.1')),

    'max_recommendations' => 3,

];
