<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Models\Conversation;
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

    public function test_resolves_legacy_dollar_paren_syntax(): void
    {
        $result = $this->resolver->resolve(
            'Hi $(first_name), your number is $(phone_number)',
            ['first_name' => 'Riya', 'phone_number' => '919999999999'],
        );

        $this->assertSame('Hi Riya, your number is 919999999999', $result);
    }

    public function test_resolves_mixed_syntax(): void
    {
        $result = $this->resolver->resolve(
            'Hi $(first_name) / {{last_name}}',
            ['first_name' => 'A', 'last_name' => 'B'],
        );

        $this->assertSame('Hi A / B', $result);
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

        $legacy = $this->resolver->resolve('Hello $(name)!', []);

        $this->assertSame('Hello $(name)!', $legacy);
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

    public function test_conversation_context_splits_contact_name(): void
    {
        $conversation = new Conversation([
            'contact_name' => 'Sparsh Thakur',
            'contact_phone' => '919876543210',
            'line_phone' => '911234567890',
        ]);

        $ctx = $this->resolver->conversationContext($conversation);

        $this->assertSame('Sparsh', $ctx['first_name']);
        $this->assertSame('Thakur', $ctx['last_name']);
        $this->assertSame('Sparsh Thakur', $ctx['full_name']);
        $this->assertSame('919876543210', $ctx['phone_number']);
        $this->assertSame('911234567890', $ctx['recipient_number']);
        $this->assertArrayHasKey('current_date', $ctx);
    }
}
