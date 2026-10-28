<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Template;

/**
 * Shared wait-state setup for templateMessage nodes (in-flow send + external template entry).
 */
final class TemplateMessageWaitHydrator
{
    /**
     * @param  array<string, mixed>  $node  Normalized node map entry
     */
    public static function isWaitable(array $node): bool
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $quickReplies = $data['quickReplies'] ?? [];
        if (is_array($quickReplies) && $quickReplies !== []) {
            return true;
        }

        return self::hasQuickReplyBranches($node);
    }

    /**
     * Arm the state as Waiting on this template node without sending the template again.
     *
     * @param  array<string, mixed>  $node
     */
    public static function hydrateWaitingState(ChatbotFlowState $state, array $node): void
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $quickReplies = $data['quickReplies'] ?? [];
        if (! is_array($quickReplies)) {
            $quickReplies = [];
        }

        $state->mergeVariables([
            '_quick_replies' => $quickReplies,
            '_quick_reply_node_id' => (string) ($node['id'] ?? ''),
            '_wait_variable_name' => 'user_response',
            '_template_entry' => true,
        ]);
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => (string) ($node['id'] ?? ''),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    public static function matchesOutboundTemplate(array $nodeData, string $outboundCode, ?int $outboundTemplateId): bool
    {
        $outboundCode = trim($outboundCode);

        if ($outboundTemplateId !== null && $outboundTemplateId > 0) {
            $nodeId = self::resolveTemplateId($nodeData);
            if ($nodeId !== null && $nodeId === $outboundTemplateId) {
                return true;
            }
        }

        $nodeCode = self::resolveTemplateCode($nodeData);
        if ($nodeCode !== '' && $outboundCode !== '' && strcasecmp($nodeCode, $outboundCode) === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveTemplateCode(array $data): string
    {
        $selected = is_array($data['selectedTemplate'] ?? null) ? $data['selectedTemplate'] : [];

        $candidates = [
            $data['templateCode'] ?? null,
            $data['template_code'] ?? null,
            $selected['template_code'] ?? null,
            $selected['code'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && CamsTemplateIdentity::isProviderCode($value)) {
                return $value;
            }
        }

        $dbId = self::resolveTemplateId($data);
        if ($dbId !== null) {
            $template = Template::query()->find($dbId);
            if ($template !== null) {
                $provider = $template->whatsappCode();
                if (filled($provider)) {
                    return (string) $provider;
                }
            }
        }

        $fallbackId = trim((string) ($data['templateId'] ?? $data['template_name'] ?? ''));
        if ($fallbackId !== '' && CamsTemplateIdentity::isProviderCode($fallbackId)) {
            return $fallbackId;
        }

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveTemplateId(array $data): ?int
    {
        $selected = is_array($data['selectedTemplate'] ?? null) ? $data['selectedTemplate'] : [];
        $dbId = $data['templateId'] ?? $selected['id'] ?? null;

        if (filled($dbId) && is_numeric($dbId)) {
            return (int) $dbId;
        }

        return null;
    }

    /**
     * True when the node has a selectable WhatsApp template (activation entry).
     *
     * @param  array<string, mixed>  $data
     */
    public static function hasConfiguredTemplate(array $data): bool
    {
        if (self::resolveTemplateId($data) !== null) {
            return true;
        }

        return self::resolveTemplateCode($data) !== '';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function hasQuickReplyBranches(array $node): bool
    {
        foreach (array_keys($node['outputs'] ?? []) as $handle) {
            $handle = (string) $handle;
            if (str_starts_with($handle, 'reply-') || str_starts_with($handle, 'reply_')) {
                $connections = $node['outputs'][$handle]['connections'] ?? [];
                if ($connections !== []) {
                    return true;
                }
            }
        }

        return false;
    }
}
