<?php

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\TemplateVariableSyntax;
use PHPUnit\Framework\TestCase;

class TemplateVariableSyntaxTest extends TestCase
{
    public function test_it_preserves_legacy_dollar_syntax(): void
    {
        $this->assertSame(
            'Hello $(first_name), code $(verificationCode)',
            TemplateVariableSyntax::normalizeBodyText('Hello $(first_name), code $(verificationCode)'),
        );
    }

    public function test_it_converts_brace_syntax_to_legacy_dollar_syntax(): void
    {
        $this->assertSame(
            'Hello $(first_name), code $(verificationCode)',
            TemplateVariableSyntax::normalizeBodyText('Hello {{first_name}}, code {{verificationCode}}'),
        );
    }

    public function test_it_extracts_variable_names_from_mixed_syntax(): void
    {
        $names = TemplateVariableSyntax::extractVariableNames('Hi {{name}} and $(legacy)');

        $this->assertSame(['name', 'legacy'], $names);
    }

    public function test_it_substitutes_variable_values(): void
    {
        $this->assertSame(
            'Hello Alice, code 1234',
            TemplateVariableSyntax::substitute('Hello $(first_name), code {{otp}}', [
                'first_name' => 'Alice',
                'otp' => '1234',
            ]),
        );
    }

    public function test_it_maps_samples_to_names(): void
    {
        $this->assertSame(
            ['first_name' => 'Alice', 'otp' => '9999'],
            TemplateVariableSyntax::mapSamplesToNames(['first_name', 'otp'], ['Alice', '9999']),
        );
    }
}
