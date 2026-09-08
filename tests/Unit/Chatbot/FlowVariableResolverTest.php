<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Support\FlowVariableResolver;
use PHPUnit\Framework\TestCase;

class FlowVariableResolverTest extends TestCase
{
    private FlowVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FlowVariableResolver();
    }

    public function test_resolves_simple_variables(): void
    {
        $result = $this->resolver->resolve('Hello {{name}}!', ['name' => 'John']);

        $this->assertSame('Hello John!', $result);
    }

    public function test_resolves_multiple_variables(): void
    {
        $result = $this->resolver->resolve(
            '{{greeting}} {{name}}, your order #{{order_id}} is ready.',
            ['greeting' => 'Hi', 'name' => 'Jane', 'order_id' => '12345'],
        );

        $this->assertSame('Hi Jane, your order #12345 is ready.', $result);
    }

    public function test_leaves_unresolved_variables_untouched(): void
    {
        $result = $this->resolver->resolve('Hello {{name}}!', []);

        $this->assertSame('Hello {{name}}!', $result);
    }

    public function test_resolves_dot_notation(): void
    {
        $result = $this->resolver->resolve(
            'Your city is {{user.city}}',
            ['user' => ['city' => 'Delhi']],
        );

        $this->assertSame('Your city is Delhi', $result);
    }

    public function test_handles_null_values(): void
    {
        $result = $this->resolver->resolve('Hello {{name}}!', ['name' => null]);

        // null values leave placeholder
        $this->assertSame('Hello {{name}}!', $result);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->resolver->resolve('', ['name' => 'test']);

        $this->assertSame('', $result);
    }

    public function test_resolves_boolean_values(): void
    {
        $result = $this->resolver->resolve('Active: {{active}}', ['active' => true]);

        $this->assertSame('Active: true', $result);
    }
}
