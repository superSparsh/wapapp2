<?php

declare(strict_types=1);

namespace Tests\Unit\FormBuilder;

use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormFieldNormalizerTest extends TestCase
{
    #[Test]
    public function it_maps_legacy_pascal_case_types(): void
    {
        $fields = FormFieldNormalizer::normalizeList([
            [
                'id' => 'phone_number',
                'type' => 'Input',
                'label' => 'Whatsapp Number',
                'name' => 'phone_number',
                'required' => true,
                'unremovable' => true,
            ],
            [
                'id' => 'first_name',
                'type' => 'Input',
                'label' => 'First Name',
                'name' => 'first_name',
                'required' => false,
            ],
            [
                'id' => 'last_name',
                'type' => 'Input',
                'label' => 'Last Name',
                'name' => 'last_name',
                'required' => false,
            ],
            [
                'id' => '4',
                'type' => 'Header',
                'label' => 'Join our list',
            ],
            [
                'id' => '3',
                'type' => 'Select',
                'label' => 'City',
                'options' => ['Delhi', 'Mumbai'],
            ],
            [
                'id' => '6',
                'type' => 'Logo',
                'label' => 'Logo',
                'src' => 'forms/logo.png',
            ],
            [
                'id' => '2',
                'type' => 'Checkbox',
                'label' => 'I agree',
                'required' => true,
            ],
            [
                'id' => '5',
                'type' => 'Paragraph',
                'label' => 'Thanks for signing up',
            ],
        ]);

        $this->assertSame([
            'phone',
            'first_name',
            'last_name',
            'header',
            'dropdown',
            'logo',
            'checkbox',
            'paragraph',
        ], array_column($fields, 'type'));

        $this->assertTrue($fields[0]['locked']);
        $this->assertSame(['Delhi', 'Mumbai'], $fields[4]['options']);
        $this->assertSame('forms/logo.png', $fields[5]['image_path']);
        $this->assertSame('Join our list', $fields[3]['text']);
        $this->assertSame('Thanks for signing up', $fields[7]['text']);
    }
}
