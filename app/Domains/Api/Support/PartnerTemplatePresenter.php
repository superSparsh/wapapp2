<?php

declare(strict_types=1);

namespace App\Domains\Api\Support;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplateMediaService;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Models\Template;

/**
 * Legacy /api/v1/templates response shape (Acelle TemplateController parity).
 */
final class PartnerTemplatePresenter
{
    public function __construct(
        private readonly TemplateMediaService $mediaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Template $template): array
    {
        $payload = $template->wizardPayload();
        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $footer = is_array($payload['footer'] ?? null) ? $payload['footer'] : [];
        $buttons = is_array($payload['buttons'] ?? null) ? $payload['buttons'] : [];

        [$buttonDescription, $buttonLink, $phoneDescription, $phoneLink] = $this->legacyButtons($buttons);

        $headerType = $this->legacyHeaderType((string) ($header['type'] ?? 'none'));
        $category = (string) $template->category;
        if ((bool) data_get($payload, 'carousel.enabled', false)) {
            $category = TemplateCategoryCatalog::CAROUSEL;
        }

        return [
            'uid' => $template->uuid,
            'name' => $template->name,
            'template_category' => TemplateCategoryCatalog::label($category),
            'body' => (string) ($body['text'] ?? $template->body_preview ?? ''),
            'header_type' => $headerType,
            'header_media' => $this->headerMedia($header),
            'header_description' => (string) ($header['text'] ?? ''),
            'footer_description' => (string) ($footer['text'] ?? ''),
            'button_description' => $buttonDescription,
            'button_link' => $buttonLink,
            'phone_button_description' => $phoneDescription,
            'phone_button_link' => $phoneLink,
            'is_unsubscribed' => (bool) ($payload['is_opt_out'] ?? false) ? 1 : 0,
            'status' => $this->legacyStatus($template),
            'status_details' => $template->rejection_reason,
            'created_at' => optional($template->created_at)?->toDateTimeString(),
            'updated_at' => optional($template->updated_at)?->toDateTimeString(),
        ];
    }

    private function legacyStatus(Template $template): string
    {
        $status = $template->status instanceof TemplateStatus
            ? $template->status
            : TemplateStatus::tryFrom((string) $template->status);

        return match ($status) {
            TemplateStatus::Approved => 'Approved',
            TemplateStatus::PendingReview => 'Submitted for approval',
            TemplateStatus::Rejected => 'Rejected',
            TemplateStatus::Draft => 'Draft',
            default => (string) $template->status,
        };
    }

    private function legacyHeaderType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'text', 'txt' => 'text',
            'image', 'img', 'picture' => 'img',
            'video' => 'video',
            'document', 'doc', 'pdf' => 'doc',
            'none', '' => null,
            default => $type,
        };
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function headerMedia(array $header): ?string
    {
        $mediaUrl = trim((string) ($header['media_url'] ?? ''));
        if ($mediaUrl !== '') {
            return $mediaUrl;
        }

        $mediaPath = trim((string) ($header['media_path'] ?? ''));
        if ($mediaPath === '') {
            return null;
        }

        return $this->mediaService->publicUrl($mediaPath);
    }

    /**
     * @param  list<mixed>  $buttons
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function legacyButtons(array $buttons): array
    {
        $buttonDescription = null;
        $buttonLink = null;
        $phoneDescription = null;
        $phoneLink = null;

        foreach ($buttons as $button) {
            if (! is_array($button)) {
                continue;
            }

            $text = trim((string) ($button['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $type = strtolower(trim((string) ($button['type'] ?? 'url')));
            $url = trim((string) ($button['url'] ?? ''));

            if (in_array($type, ['phone', 'phone_number', 'call'], true)) {
                if ($phoneDescription === null) {
                    $phoneDescription = $text;
                    $phoneLink = $url !== '' ? $url : null;
                }

                continue;
            }

            if ($buttonDescription === null) {
                $buttonDescription = $text;
                $buttonLink = $url !== '' ? $url : null;
            }
        }

        return [$buttonDescription, $buttonLink, $phoneDescription, $phoneLink];
    }
}
