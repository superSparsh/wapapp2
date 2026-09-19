<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Http\Requests\SaveCarouselRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaveCarouselRequestTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        $request = SaveCarouselRequest::create('/templates/builder/1/carousel', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        // Run prepareForValidation via reflection (same as FormRequest pipeline).
        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }

    public function test_rejects_missing_media_and_buttons(): void
    {
        $validator = $this->validate([
            'cards' => [
                ['header' => 'IMAGE', 'body' => 'One', 'media_path' => '', 'use_url' => false, 'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => '', 'url' => ''],
                ]],
                ['header' => 'IMAGE', 'body' => 'Two', 'media_path' => '', 'use_url' => false, 'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => '', 'url' => ''],
                ]],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_rejects_mismatched_button_types(): void
    {
        $validator = $this->validate([
            'cards' => [
                [
                    'header' => 'IMAGE',
                    'body' => 'One',
                    'media_path' => 'templates/a.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Help', 'url' => ''],
                    ],
                ],
                [
                    'header' => 'IMAGE',
                    'body' => 'Two',
                    'media_path' => 'templates/b.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Visit', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('cards.1.buttons'));
    }

    public function test_rejects_duplicate_quick_reply_texts(): void
    {
        $validator = $this->validate([
            'cards' => [
                [
                    'header' => 'IMAGE',
                    'body' => 'One',
                    'media_path' => 'templates/a.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Help', 'url' => ''],
                    ],
                ],
                [
                    'header' => 'IMAGE',
                    'body' => 'Two',
                    'media_path' => 'templates/b.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'help', 'url' => ''],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_rejects_mixed_header_types(): void
    {
        $validator = $this->validate([
            'cards' => [
                [
                    'header' => 'IMAGE',
                    'body' => 'One',
                    'media_path' => 'templates/a.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Help', 'url' => ''],
                    ],
                ],
                [
                    'header' => 'VIDEO',
                    'body' => 'Two',
                    'media_path' => 'templates/b.mp4',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Info', 'url' => ''],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('cards.1.header'));
    }

    public function test_accepts_consistent_valid_cards(): void
    {
        $validator = $this->validate([
            'cards' => [
                [
                    'header' => 'IMAGE',
                    'body' => 'One',
                    'media_path' => 'templates/a.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Visit', 'url' => 'https://a.example.com'],
                        ['type' => 'QUICK_REPLY', 'text' => 'Help', 'url' => ''],
                    ],
                ],
                [
                    'header' => 'IMAGE',
                    'body' => 'Two',
                    'media_path' => 'templates/b.jpg',
                    'use_url' => false,
                    'buttons' => [
                        ['type' => 'URL', 'text' => 'Visit', 'url' => 'https://b.example.com'],
                        ['type' => 'QUICK_REPLY', 'text' => 'Info', 'url' => ''],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }
}
