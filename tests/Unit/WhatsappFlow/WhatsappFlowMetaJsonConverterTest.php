<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsappFlow;

use App\Domains\WhatsappFlow\Support\WhatsappFlowMetaJsonConverter;
use PHPUnit\Framework\TestCase;

class WhatsappFlowMetaJsonConverterTest extends TestCase
{
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
        $this->assertArrayHasKey('screen_1', $result['routing_model']);
        $this->assertSame('screen_2', $result['routing_model']['screen_1'][0]);
        $this->assertTrue($result['screens'][1]['terminal']);
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
                        ['type' => 'image', 'src' => 'https://example.com/logo.png', 'width' => 300, 'height' => 150],
                        ['type' => 'text-display', 'text' => 'Thank you for your submission.'],
                        ['type' => 'footer', 'label' => 'Submit Application'],
                    ],
                    'next_screen' => null,
                ],
            ],
        ]);

        $this->assertSame('6.3', $result['version']);
        $this->assertCount(1, $result['screens']);

        $children = $result['screens'][0]['layout']['children'][0]['children'];

        // Verify Large Heading
        $this->assertSame('TextHeading', $children[0]['type']);
        $this->assertSame('Main Registration', $children[0]['text']);

        // Verify Small Heading
        $this->assertSame('TextSubheading', $children[1]['type']);
        $this->assertSame('Personal Info', $children[1]['text']);

        // Verify Caption
        $this->assertSame('TextCaption', $children[2]['type']);

        // Verify Text Input with helper-text
        $this->assertSame('TextInput', $children[3]['type']);
        $this->assertSame('text', $children[3]['input-type']);
        $this->assertSame('Your legal name', $children[3]['helper-text']);

        // Verify Email
        $this->assertSame('TextInput', $children[4]['type']);
        $this->assertSame('email', $children[4]['input-type']);
        $this->assertSame('name@domain.com', $children[4]['helper-text']);

        // Verify Phone
        $this->assertSame('TextInput', $children[5]['type']);
        $this->assertSame('phone', $children[5]['input-type']);

        // Verify Password
        $this->assertSame('TextInput', $children[7]['type']);
        $this->assertSame('password', $children[7]['input-type']);

        // Verify TextArea (Paragraph)
        $this->assertSame('TextArea', $children[8]['type']);

        // Verify DatePicker
        $this->assertSame('DatePicker', $children[9]['type']);

        // Verify RadioButtonsGroup
        $this->assertSame('RadioButtonsGroup', $children[10]['type']);
        $this->assertCount(3, $children[10]['data-source']);

        // Verify CheckboxGroup
        $this->assertSame('CheckboxGroup', $children[11]['type']);
        $this->assertCount(3, $children[11]['data-source']);

        // Verify Dropdown
        $this->assertSame('Dropdown', $children[12]['type']);
        $this->assertCount(3, $children[12]['data-source']);

        // Verify OptIn
        $this->assertSame('OptIn', $children[13]['type']);

        // Verify Image
        $this->assertSame('Image', $children[14]['type']);
        $this->assertSame('https://example.com/logo.png', $children[14]['src']);

        // Verify TextBody
        $this->assertSame('TextBody', $children[15]['type']);

        // Verify Footer
        $this->assertSame('Footer', $children[16]['type']);
        $this->assertSame('Submit Application', $children[16]['label']);
    }

    public function test_empty_flow_returns_minimal_structure(): void
    {
        $result = WhatsappFlowMetaJsonConverter::convert(['screens' => []]);

        $this->assertSame('6.3', $result['version']);
        $this->assertSame([], $result['screens']);
    }
}
