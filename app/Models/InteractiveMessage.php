<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractiveMessage extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'content',
        'whatsapp_line_id',
        'team_member_id',
        'team_member_name',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function whatsappLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappLine::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /** @return array<string, mixed> */
    public function normalizedContent(): array
    {
        $content = $this->content ?? [];

        return [
            'body' => (string) ($content['body'] ?? ''),
            'footer' => (string) ($content['footer'] ?? ''),
            'buttons' => array_values($content['buttons'] ?? []),
            'header' => $content['header'] ?? ['type' => 'none', 'text' => '', 'media_path' => null],
            'list_button_text' => (string) ($content['list_button_text'] ?? ''),
            'list_sections' => array_values($content['list_sections'] ?? []),
            'catalog_id' => (string) ($content['catalog_id'] ?? ''),
            'product_retailer_id' => (string) ($content['product_retailer_id'] ?? ''),
            'flow_id' => (string) ($content['flow_id'] ?? ''),
            'flow_cta' => (string) ($content['flow_cta'] ?? ''),
        ];
    }
}
