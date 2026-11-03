<?php

use App\Services\Ai\OpenAiService;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

beforeEach(function () {
    config(['openai.api_key' => 'test-only-not-a-real-key']);
});

function openAiMenuItem(int $id): array
{
    return [
        'id' => $id,
        'name' => 'Item '.$id,
        'description' => 'A dish',
        'category' => 'main-course',
        'price' => 12.0,
        'spice_level' => null,
        'protein_type' => null,
        'dietary_type' => null,
        'flavor_profile' => null,
    ];
}

test('it retains only integer IDs from the supplied menu items', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [[
                'message' => ['content' => '{"recommended_item_ids":[2,999,2,"1"]}'],
            ]],
        ]),
    ]);

    $ids = (new OpenAiService)->recommend([
        openAiMenuItem(1),
        openAiMenuItem(2),
    ], ['category' => 'main-course']);

    expect($ids)->toBe([2]);
});

test('malformed OpenAI output falls back to deterministic eligible recommendations', function (string $content, string $finishReason) {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [[
                'message' => ['content' => $content],
                'finish_reason' => $finishReason,
            ]],
        ]),
    ]);

    expect((new OpenAiService)->recommend([openAiMenuItem(1)], []))->toBe([1]);
})->with([
    'missing IDs' => ['{"unexpected":"response"}', 'stop'],
    'invalid JSON' => ['not JSON', 'stop'],
    'object instead of ID list' => ['{"recommended_item_ids":{"first":1}}', 'stop'],
    'untrusted extra claims' => ['{"recommended_item_ids":[1],"allergy_safe":true}', 'stop'],
    'truncated response' => ['{"recommended_item_ids":[1]}', 'length'],
    'refusal' => ['null', 'content_filter'],
    'empty IDs' => ['{"recommended_item_ids":[]}', 'stop'],
    'invented IDs' => ['{"recommended_item_ids":[999]}', 'stop'],
    'string IDs' => ['{"recommended_item_ids":["1"]}', 'stop'],
]);

test('OpenAI cannot return more than three candidates even if configuration is raised', function () {
    config(['ai.max_recommendations' => 10]);
    OpenAI::fake([CreateResponse::fake([
        'choices' => [['message' => ['content' => '{"recommended_item_ids":[4,2,1,3]}']]],
    ])]);

    expect((new OpenAiService)->recommend(array_map(openAiMenuItem(...), [1, 2, 3, 4]), []))
        ->toBe([4, 2, 1]);
});
