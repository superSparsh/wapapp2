<?php

declare(strict_types=1);

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Support\AiTestBotUserMessage;
use PHPUnit\Framework\TestCase;

class AiTestBotUserMessageTest extends TestCase
{
    public function test_detects_all_providers_exhausted_payload(): void
    {
        $raw = 'Tittu Gateway chat failed: {"error":"all_providers_exhausted","message":"All eligible AI providers are exhausted or cooling down. Attempted: groq-free [default] (Credential Primary Account (default) proactive limit reached), gemini-free [default] (Provider gemini-free returned HTTP 402: Your prepayment credits are depleted."}';

        $this->assertTrue(AiTestBotUserMessage::isProviderUnavailable($raw));
        $message = AiTestBotUserMessage::forTestBot($raw);
        $this->assertNotNull($message);
        $this->assertStringContainsString('exhausted', mb_strtolower((string) $message));
        $this->assertStringContainsString('cooling down', mb_strtolower((string) $message));
    }

    public function test_includes_cooldown_time_from_json(): void
    {
        $raw = 'Tittu Gateway chat failed: {"error":"all_providers_exhausted","message":"cooling down","cooldownRemainingSeconds":125}';

        $this->assertSame(125, AiTestBotUserMessage::extractCooldownSeconds($raw));
        $message = (string) AiTestBotUserMessage::forTestBot($raw);
        $this->assertStringContainsString('2 minutes', $message);
        $this->assertStringContainsString('cooling down', mb_strtolower($message));
    }

    public function test_parses_human_cooldown_text(): void
    {
        $raw = 'all_providers_exhausted — try again in 45 seconds';

        $this->assertSame(45, AiTestBotUserMessage::extractCooldownSeconds($raw));
        $this->assertStringContainsString('45 seconds', (string) AiTestBotUserMessage::forTestBot($raw));
    }

    public function test_leaves_unrelated_errors_alone(): void
    {
        $raw = 'No API key configured for this bot\'s provider.';

        $this->assertFalse(AiTestBotUserMessage::isProviderUnavailable($raw));
        $this->assertNull(AiTestBotUserMessage::forTestBot($raw));
    }
}
