<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Support\PublicId;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Application-layer proxy for Knowledge Base operations.
 * Chroma (via Python AI service) is the source of truth — no MySQL KB mirror.
 */
class KnowledgeBaseProxyService
{
    public function __construct(
        private readonly AiPythonClient $client,
    ) {}

    public function clientId(): string
    {
        $tenantId = tenant('id');
        if (! filled($tenantId)) {
            throw new RuntimeException('Tenant context is required for Knowledge Base operations.');
        }

        return (string) $tenantId;
    }

    public function resolveBot(?string $botId): ?AiBot
    {
        if (filled($botId)) {
            return PublicId::find(AiBot::class, $botId)
                ?? AiBot::query()->where('id', $botId)->first();
        }

        return AiBot::query()->where('is_default', true)->first()
            ?? AiBot::query()->orderBy('id')->first();
    }

    public function botUid(?AiBot $bot): ?string
    {
        return $bot?->uuid;
    }

    /**
     * @return array{provider: string, api_key: ?string, chat_model: string, embedding_model: string}
     */
    public function resolveProviderConfig(?AiBot $bot): array
    {
        if ($bot !== null) {
            return $bot->resolveProvider();
        }

        $key = AiProviderKey::query()
            ->where('is_active', true)
            ->orderByDesc('is_validated')
            ->orderByDesc('id')
            ->first();

        if ($key === null) {
            return [
                'provider' => 'openai',
                'api_key' => null,
                'chat_model' => 'gpt-4o-mini',
                'embedding_model' => 'text-embedding-3-small',
            ];
        }

        $provider = is_object($key->provider) && property_exists($key->provider, 'value')
            ? (string) $key->provider->value
            : (string) $key->provider;

        return [
            'provider' => $provider,
            'api_key' => $key->api_key,
            'chat_model' => $key->chat_model ?? 'gpt-4o-mini',
            'embedding_model' => $key->embedding_model ?? 'text-embedding-3-small',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function list(?string $botId = null, int $limit = 10, int $offset = 0): array
    {
        $bot = $this->resolveBot($botId);
        $query = [
            'limit' => max(1, min($limit, 100)),
            'offset' => max(0, $offset),
        ];
        if ($uid = $this->botUid($bot)) {
            $query['bot_id'] = $uid;
        }

        return $this->client->get('/knowledge_base/'.$this->clientId(), $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageInfo(?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $query = [];
        if ($uid = $this->botUid($bot)) {
            $query['bot_id'] = $uid;
        }

        return $this->client->get('/client_storage_info/'.$this->clientId(), $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function download(?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $query = [];
        if ($uid = $this->botUid($bot)) {
            $query['bot_id'] = $uid;
        }

        return $this->client->get('/knowledge_base/'.$this->clientId().'/download', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $query = [];
        if ($uid = $this->botUid($bot)) {
            $query['bot_id'] = $uid;
        }

        return $this->client->delete('/clear_client_data/'.$this->clientId(), $query);
    }

    /**
     * @param  list<string>  $documents
     * @param  list<array<string, mixed>>  $metadata
     * @return array<string, mixed>
     */
    public function addManualContent(array $documents, array $metadata = [], ?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $cfg = $this->requireApiKey($bot);

        $payload = [
            'client_id' => $this->clientId(),
            'customer_id' => $this->clientId(),
            'api_key' => $cfg['api_key'],
            'documents' => array_values($documents),
            'metadata' => array_values($metadata),
            'provider' => $cfg['provider'],
            'embedding_model' => $cfg['embedding_model'],
            'chat_model' => $cfg['chat_model'],
            'temperature' => $bot?->temperature ?? 0.3,
        ];

        if ($uid = $this->botUid($bot)) {
            $payload['bot_id'] = $uid;
        }

        return $this->client->postJson('/add_manual_content', $payload);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function uploadFile(UploadedFile $file, array $metadata = [], ?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $cfg = $this->requireApiKey($bot);

        $query = [
            'client_id' => $this->clientId(),
            'customer_id' => $this->clientId(),
            'api_key' => $cfg['api_key'],
            'provider' => $cfg['provider'],
            'embedding_model' => $cfg['embedding_model'],
        ];
        if ($uid = $this->botUid($bot)) {
            $query['bot_id'] = $uid;
        }

        $fields = [];
        if ($metadata !== []) {
            $fields['metadata'] = json_encode($metadata);
        }

        return $this->client->postMultipart('/upload_file', $file, $query, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function scrapeWebsite(string $url, int $maxPages = 15): array
    {
        return $this->client->postJson('/scrape_website', [
            'url' => $url,
        ], [
            'url' => $url,
            'max_pages' => $maxPages,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractTextFromFile(UploadedFile $file): array
    {
        return $this->client->postMultipart('/extract_text_from_file', $file);
    }

    /**
     * @return array<string, mixed>
     */
    public function structureText(string $text, ?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $cfg = $this->requireApiKey($bot);

        return $this->client->postJson('/structure_text', [
            'text' => $text,
            'api_key' => $cfg['api_key'],
            'provider' => $cfg['provider'],
            'model_name' => $cfg['chat_model'],
            'embedding_model' => $cfg['embedding_model'],
            'customer_id' => $this->clientId(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reindex(?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);
        $cfg = $this->requireApiKey($bot);

        $payload = [
            'client_id' => $this->clientId(),
            'customer_id' => $this->clientId(),
            'api_key' => $cfg['api_key'],
            'provider' => $cfg['provider'],
            'embedding_model' => $cfg['embedding_model'],
        ];
        if ($uid = $this->botUid($bot)) {
            $payload['bot_id'] = $uid;
        }

        return $this->client->postJson('/reindex', $payload);
    }

    /**
     * @param  list<array{role: string, content: string}>  $chatHistory
     * @return array<string, mixed>
     */
    public function processQuery(string $queryText, ?string $botId = null, array $chatHistory = []): array
    {
        $bot = $this->resolveBot($botId);
        $cfg = $this->requireApiKey($bot);

        $payload = [
            'client_id' => $this->clientId(),
            'customer_id' => $this->clientId(),
            'query_text' => $queryText,
            'api_key' => $cfg['api_key'],
            'provider' => $cfg['provider'],
            'chat_model' => $cfg['chat_model'],
            'embedding_model' => $cfg['embedding_model'],
            'temperature' => $bot?->temperature ?? 0.3,
            'system_prompt' => $bot?->system_prompt,
            'chat_history' => $chatHistory,
        ];

        if ($uid = $this->botUid($bot)) {
            $payload['bot_id'] = $uid;
        }

        if (filled($bot?->business_information)) {
            $payload['system_prompt'] = trim(
                (string) ($payload['system_prompt'] ?? '')."\n\nBusiness context: ".$bot->business_information
            );
        }

        return $this->client->postJson('/process_query', $payload);
    }

    /**
     * @return array{provider: string, api_key: string, chat_model: string, embedding_model: string}
     */
    private function requireApiKey(?AiBot $bot): array
    {
        $cfg = $this->resolveProviderConfig($bot);
        if (empty($cfg['api_key'])) {
            throw new RuntimeException('No API key configured for the selected provider. Add one in AI Assistant → API Settings.');
        }

        return $cfg;
    }
}
