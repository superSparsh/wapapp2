<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsappFlow;

use App\Domains\WhatsappFlow\Support\WhatsappFlowMetaJsonConverter;
use PHPUnit\Framework\TestCase;

class WhatsappFlowMetaJsonConverterTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $screen
     * @return array<int, array<string, mixed>>
     */
    private function formChildren(array $screen): array
    {
        foreach ($screen['layout']['children'] as $child) {
            if (($child['type'] ?? '') === 'Form') {
                return $child['children'];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $screen
     * @return array<int, array<string, mixed>>
     */
    private function layoutWidgets(array $screen): array
    {
        return array_values(array_filter(
            $screen['layout']['children'],
            fn (array $child) => ($child['type'] ?? '') !== 'Form',
        ));
    }

    public function test_converts_internal_schema_to_meta_json(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'first_screen' => 'screen_1',
            'screens' => [
                [
                    'id' => 'screen_1',
                    'title' => 'Contact Info',
                    'fields' => [
                        ['type' => 'text', 'name' => 'full_name', 'label' => 'Full Name', 'required' => true],
                        ['type' => 'footer', 'label' => 'Continue'],
                    ],
                    'next_screen' => 'screen_2',
                ],
                [
                    'id' => 'screen_2',
                    'title' => 'Confirm',
                    'fields' => [
                        ['type' => 'text-display', 'label' => 'Thanks', 'text' => 'Please confirm'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $this->assertSame('6.3', $result['version']);
        $this->assertCount(2, $result['screens']);
        // Digits stripped: screen_1 → screen, screen_2 → screen_ONE (unique)
        $this->assertSame('screen', $result['screens'][0]['id']);
        $this->assertSame('screen_ONE', $result['screens'][1]['id']);
        $this->assertSame(['screen_ONE'], $result['routing_model']['screen']);
        $this->assertTrue($result['screens'][1]['terminal']);
        $this->assertMatchesRegularExpression('/^[A-Za-z_]+$/', $result['screens'][0]['id']);
        $this->assertMatchesRegularExpression('/^[A-Za-z_]+$/', $result['screens'][1]['id']);
    }

    public function test_screen_ids_contain_no_digits(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'screens' => [
                [
                    'id' => 'SCREEN_ScreenOne',
                    'title' => 'DETAILS',
                    'fields' => [
                        ['type' => 'text', 'name' => 'full_name', 'label' => 'Name', 'required' => true],
                        ['type' => 'footer', 'label' => 'Next'],
                    ],
                    'next_screen' => 'screen_2',
                ],
                [
                    'id' => 'screen_2',
                    'title' => 'address',
                    'fields' => [
                        ['type' => 'text', 'name' => 'city', 'label' => 'City'],
                        ['type' => 'footer', 'label' => 'Submit'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $this->assertSame('SCREEN_ScreenOne', $result['screens'][0]['id']);
        $this->assertSame('screen', $result['screens'][1]['id']);
        $this->assertSame(['screen'], $result['routing_model']['SCREEN_ScreenOne']);
        $this->assertSame([], $result['routing_model']['screen']);

        $encoded = json_encode($result);
        $this->assertDoesNotMatchRegularExpression('/"id"\s*:\s*"[^"]*[0-9][^"]*"/', $encoded);
        $this->assertStringNotContainsString('screen_2', $encoded);
    }

    public function test_converts_all_field_types_and_helper_texts(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'first_screen' => 'screen_1',
            'screens' => [
                [
                    'id' => 'screen_1',
                    'title' => 'Comprehensive Form',
                    'fields' => [
                        ['type' => 'large-heading', 'text' => 'Main Registration'],
                        ['type' => 'small-heading', 'text' => 'Personal Info'],
                        ['type' => 'caption', 'text' => 'Please fill out all required fields.'],
                        ['type' => 'text', 'name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'helper_text' => 'Your legal name'],
                        ['type' => 'email', 'name' => 'email_address', 'label' => 'Email Address', 'required' => true, 'helper_text' => 'name@domain.com'],
                        ['type' => 'phone', 'name' => 'mobile_number', 'label' => 'Mobile Phone', 'required' => true, 'helper_text' => '10 digits mobile'],
                        ['type' => 'number', 'name' => 'user_age', 'label' => 'Age', 'required' => false],
                        ['type' => 'password', 'name' => 'user_pin', 'label' => '4-Digit PIN', 'required' => true],
                        ['type' => 'paragraph', 'name' => 'user_bio', 'label' => 'Bio / Remarks', 'required' => false],
                        ['type' => 'date', 'name' => 'dob', 'label' => 'Date of Birth', 'required' => true],
                        ['type' => 'radio', 'name' => 'gender', 'label' => 'Gender', 'options' => ['Male', 'Female', 'Other'], 'required' => true],
                        ['type' => 'checkbox', 'name' => 'interests', 'label' => 'Interests', 'options' => ['Tech', 'Marketing', 'Design']],
                        ['type' => 'dropdown', 'name' => 'country', 'label' => 'Country', 'options' => ['India', 'USA', 'UK']],
                        ['type' => 'opt-in', 'name' => 'terms_agreement', 'label' => 'I agree to Terms & Conditions', 'required' => true],
                        ['type' => 'image', 'base64image' => 'iVBORw0KGgo=', 'width' => 300, 'height' => 150],
                        ['type' => 'text-display', 'text' => 'Thank you for your submission.'],
                        ['type' => 'footer', 'label' => 'Submit Application'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $screen = $result['screens'][0];
        $widgets = $this->layoutWidgets($screen);
        $formChildren = $this->formChildren($screen);

        $this->assertSame('TextHeading', $widgets[0]['type']);
        $this->assertSame('Image', $widgets[3]['type']);
        $this->assertSame('text', $formChildren[0]['input-type']);
        $this->assertSame('^[0-9]{10}$', $formChildren[2]['pattern']);

        $footer = $formChildren[array_key_last($formChildren)];
        $this->assertSame('complete', $footer['on-click-action']['name']);
        $this->assertArrayHasKey('full_name', $footer['on-click-action']['payload']);
    }

    public function test_empty_flow_returns_minimal_structure(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert(['screens' => []]);

        $this->assertSame('6.3', $result['version']);
        $this->assertSame([], $result['screens']);
    }

    public function test_image_prefers_base64image_over_url(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'screens' => [
                [
                    'id' => 's1',
                    'title' => 'Media',
                    'fields' => [
                        [
                            'type' => 'image',
                            'base64image' => 'iVBORw0KGgo=',
                            'src' => 'https://example.com/ignored.png',
                            'width' => 120,
                            'height' => 80,
                        ],
                        ['type' => 'text', 'name' => 'note', 'label' => 'Note'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $image = $this->layoutWidgets($result['screens'][0])[0];
        $this->assertSame('Image', $image['type']);
        $this->assertSame('iVBORw0KGgo=', $image['src']);
    }

    public function test_empty_complete_payload_encodes_as_json_object(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'screens' => [
                [
                    'id' => 's1',
                    'title' => 'Display only',
                    'fields' => [
                        ['type' => 'large-heading', 'text' => 'Hello'],
                        ['type' => 'text-display', 'text' => 'No inputs here'],
                        ['type' => 'footer', 'label' => 'Done'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $footer = $this->formChildren($result['screens'][0])[0];
        $this->assertSame('complete', $footer['on-click-action']['name']);
        $this->assertInstanceOf(\stdClass::class, $footer['on-click-action']['payload']);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"payload":{}', $encoded);
    }

    public function test_navigate_payload_only_includes_current_screen_inputs(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'screens' => [
                [
                    'id' => 'screen_1',
                    'title' => 'First',
                    'fields' => [
                        ['type' => 'large-heading', 'text' => 'Step 1'],
                        ['type' => 'text', 'name' => 'field_a', 'label' => 'A'],
                        ['type' => 'footer', 'label' => 'Next'],
                    ],
                    'next_screen' => 'screen_2',
                ],
                [
                    'id' => 'screen_2',
                    'title' => 'Second',
                    'fields' => [
                        ['type' => 'text', 'name' => 'field_b', 'label' => 'B'],
                        ['type' => 'footer', 'label' => 'Submit'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $firstId = $result['screens'][0]['id'];
        $secondId = $result['screens'][1]['id'];

        $navPayload = $this->formChildren($result['screens'][0])[1]['on-click-action']['payload'];
        $this->assertSame([
            'field_a' => '${screen.'.$firstId.'.form.field_a}',
        ], $navPayload);

        $completePayload = $this->formChildren($result['screens'][1])[1]['on-click-action']['payload'];
        $this->assertSame([
            'field_a' => '${screen.'.$firstId.'.form.field_a}',
            'field_b' => '${screen.'.$secondId.'.form.field_b}',
        ], $completePayload);
    }

    public function test_skips_url_only_and_empty_images(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert([
            'screens' => [
                [
                    'id' => 's1',
                    'title' => 'Media',
                    'fields' => [
                        ['type' => 'image', 'src' => 'https://example.com/logo.png'],
                        ['type' => 'image', 'src' => ''],
                        ['type' => 'text', 'name' => 'ok', 'label' => 'OK'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $this->assertSame([], $this->layoutWidgets($result['screens'][0]));
        $formChildren = $this->formChildren($result['screens'][0]);
        $this->assertSame('TextInput', $formChildren[0]['type']);
        $this->assertSame('Footer', $formChildren[1]['type']);
    }
}
