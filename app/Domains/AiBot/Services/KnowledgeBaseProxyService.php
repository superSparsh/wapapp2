<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\LegacyCustomerMigration;
use App\Support\PublicId;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Application-layer proxy for Knowledge Base operations.
 * Chroma (via Python AI service) is the source of truth - no MySQL KB mirror.
 *
 * Collection keys must match legacy when data was indexed there:
 * client_id = legacy_customer_id (numeric customers.id)
 * bot_id    = legacy_bot_uid (ai_bots.uid / PHP uniqid) - NOT numeric id, NOT new uuid
 */
class KnowledgeBaseProxyService
{
    public function __construct(
        private readonly AiPythonClient $client,
    ) {}

    public function clientId(): string
    {
        return $this->chromaClientId();
    }

    /**
     * Chroma client key - prefer numeric legacy customer id so migrated tenants
     * hit the same collections as the old app.
     */
    public function chromaClientId(): string
    {
        $legacyCustomerId = data_get(tenant()?->settings, 'legacy_customer_id');
        if (filled($legacyCustomerId)) {
            return (string) $legacyCustomerId;
        }

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
        return $this->chromaBotId($bot);
    }

    /**
     * Chroma bot key - legacy ExternalApiService sends ai_bots.uid (uniqid string).
     */
    public function chromaBotId(?AiBot $bot): ?string
    {
        if ($bot === null) {
            return null;
        }

        if (filled($bot->legacy_bot_uid)) {
            return (string) $bot->legacy_bot_uid;
        }

        $fromMap = $this->legacyBotUidFromMigrationMap((int) $bot->id);
        if ($fromMap !== null) {
            $bot->forceFill(['legacy_bot_uid' => $fromMap])->saveQuietly();

            return $fromMap;
        }

        // New (non-migrated) bots: use public uuid going forward.
        return $bot->uuid;
    }

    private function legacyBotUidFromMigrationMap(int $newBotId): ?string
    {
        $tenantId = tenant('id');
        if (! filled($tenantId)) {
            return null;
        }

        return tenancy()->central(function () use ($tenantId, $newBotId): ?string {
            $record = LegacyCustomerMigration::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->first();

            // Prefer explicit uid map if present (newer migrations).
            $uidByNew = $record?->report['id_map']['ai_bot_uid_by_new'] ?? null;
            if (is_array($uidByNew) && filled($uidByNew[(string) $newBotId] ?? null)) {
                return (string) $uidByNew[(string) $newBotId];
            }

            return null;
        });
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
     * Delete one knowledge-base chunk by Chroma document ID.
     *
     * @return array<string, mixed>
     */
    public function deleteDocument(string $documentId, ?string $botId = null): array
    {
        $bot = $this->resolveBot($botId);

        $payload = [
            'client_id' => $this->clientId(),
            'document_id' => $documentId,
        ];
        if ($uid = $this->botUid($bot)) {
            $payload['bot_id'] = $uid;
        }

        // Prefer POST (firewall/proxy friendly). Fall back to DELETE path if needed.
        try {
            return $this->client->postJson('/delete_kb_document', $payload);
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }

            $query = [
                'document_id' => $documentId,
            ];
            if ($uid = $this->botUid($bot)) {
                $query['bot_id'] = $uid;
            }

            return $this->client->delete('/knowledge_base/'.$this->clientId().'/documents', $query);
        }
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

        // Remote FastAPI (older Pydantic) rejects JSON null for str fields → HTTP 422.
        // Always send strings / lists, never null, for process_query.
        $systemPrompt = trim((string) ($bot?->system_prompt ?? ''));
        if (filled($bot?->business_information)) {
            $systemPrompt = trim($systemPrompt."\n\nBusiness context: ".$bot->business_information);
        }

        $isTittu = $bot?->provider === AiProvider::Tittu
            || strtolower((string) ($cfg['provider'] ?? '')) === AiProvider::Tittu->value;

        if ($isTittu) {
            $systemPrompt = $this->buildTittuRagSystemPrompt($systemPrompt);
        }

        $temperature = (float) ($bot?->temperature ?? 0.3);
        // Tittu gateway models stay more faithful to KB at lower temperature.
        if ($isTittu) {
            $temperature = min($temperature, 0.2);
        }

        // Keep recent turns only for Tittu so long chat history does not drown KB context.
        $history = array_values($chatHistory);
        if ($isTittu && count($history) > 8) {
            $history = array_slice($history, -8);
        }

        $payload = [
            'client_id' => $this->clientId(),
            'customer_id' => $this->clientId(),
            'query_text' => $queryText,
            'api_key' => (string) $cfg['api_key'],
            'provider' => (string) $cfg['provider'],
            'chat_model' => (string) ($cfg['chat_model'] ?? 'auto'),
            'embedding_model' => (string) ($cfg['embedding_model'] ?? 'auto'),
            'temperature' => $temperature,
            'system_prompt' => $systemPrompt,
            'chat_history' => $history,
        ];

        if ($uid = $this->botUid($bot)) {
            $payload['bot_id'] = (string) $uid;
        }

        return $this->client->postJson('/process_query', $payload);
    }

    /**
     * Tittu-only RAG instructions: identity + facts must come from KB context,
     * WhatsApp-short replies, no invented bot names / prices.
     */
    private function buildTittuRagSystemPrompt(string $existing): string
    {
        $tittuBlock = <<<'PROMPT'
You are a WhatsApp client-relationship assistant. Your name, tone, project facts, prices, and rules come ONLY from the knowledge below (training guide / KB). If the knowledge names you (for example Lakshmi), use that identity. Never invent a name like "WhatsApp Support Bot" or claim to be a generic support bot.

Knowledge:
{context}

Reply rules:
1. Answer the user's question first, then at most one short follow-up question.
2. Keep replies to 1–3 short WhatsApp sentences. Sound natural, warm, and confident—not like a brochure or legal disclaimer.
3. Match the user's language (English, Hindi, Hinglish, or Marathi). If they type Hindi/Marathi in Roman letters, reply the same way.
4. Use ONLY facts from the knowledge. Do not invent prices, offers, EMI schemes, approvals, inventory, travel times, email addresses, or phone numbers.
5. If the knowledge does not contain the answer, say you do not have that detail yet and offer the next helpful step (for example a site visit or calling the public number from the knowledge).
6. Be persuasive by inviting them to visit/experience the project—never fake urgency or guarantees.
7. Format lightly for WhatsApp (*bold* sparingly). No long bullet essays unless the user asks for a full list.
PROMPT;

        $existing = trim($existing);
        if ($existing === '') {
            return $tittuBlock;
        }

        if (! str_contains($existing, '{context}')) {
            return $existing."\n\n".$tittuBlock;
        }

        return $existing."\n\n".$tittuBlock;
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
