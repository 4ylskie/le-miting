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

    'driver' => env('AI_DRIVER', 'mock'),

    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    'max_recommendations' => 6,

];
