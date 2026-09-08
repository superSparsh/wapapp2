<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatbotNodeType: string
{
    case WelcomeMessage = 'welcomeMessage';
    case InteractiveMessage = 'interactiveMessage';
    case MediaMessage = 'mediaMessage';
    case WaitForResponse = 'waitForResponse';
    case Delay = 'delay';
    case Condition = 'condition';
    case EnhancedCondition = 'enhancedCondition';
    case DateTimeCondition = 'dateTimeCondition';
    case FunctionCall = 'functionCall';
    case HttpRequest = 'httpRequest';
    case TemplateMessage = 'templateMessage';
    case WhatsappFlowTemplate = 'whatsappFlowTemplate';
    case CarouselTemplate = 'carouselTemplate';
    case TypingIndicator = 'typingIndicator';
    case JumpToStep = 'jumpToStep';
    case NaturalLanguage = 'naturalLanguage';

    /**
     * @return array<string, string>
     */
    public static function categoryMap(): array
    {
        return [
            'Messages' => implode(',', [
                self::WelcomeMessage->value,
                self::TemplateMessage->value,
                self::InteractiveMessage->value,
                self::CarouselTemplate->value,
                self::WhatsappFlowTemplate->value,
                self::MediaMessage->value,
                self::TypingIndicator->value,
            ]),
            'Logic & Control' => implode(',', [
                self::Condition->value,
                self::EnhancedCondition->value,
                self::DateTimeCondition->value,
                self::WaitForResponse->value,
                self::JumpToStep->value,
            ]),
            'Integration & AI' => implode(',', [
                self::NaturalLanguage->value,
                self::FunctionCall->value,
                self::HttpRequest->value,
            ]),
            'Timing' => implode(',', [
                self::Delay->value,
            ]),
        ];
    }
}
