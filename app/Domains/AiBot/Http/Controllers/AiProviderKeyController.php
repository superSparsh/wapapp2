<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Http\Requests\StoreProviderKeyRequest;
use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Http\Controllers\Controller;
use App\Models\AiProviderKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiProviderKeyController extends Controller
{
    public function __construct(
        private readonly AiProviderKeyService $keyService,
    ) {}

    public function index(): View
    {
        $keys = AiProviderKey::query()
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('ai-bots.provider-keys', [
            'keys' => $keys,
        ]);
    }

    public function store(StoreProviderKeyRequest $request): RedirectResponse
    {
        $this->keyService->create($request->validated());

        return redirect()
            ->route('ai-bots.provider-keys.index')
            ->with('status', 'Provider key added successfully.');
    }

    public function update(Request $request, AiProviderKey $provider_key): RedirectResponse
    {
        $validated = $request->validate([
            'chat_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'embedding_dimensions' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->keyService->update($provider_key, $validated);

        return redirect()
            ->route('ai-bots.provider-keys.index')
            ->with('status', 'Provider key updated successfully.');
    }

    public function destroy(AiProviderKey $provider_key): RedirectResponse
    {
        $this->keyService->delete($provider_key);

        return redirect()
            ->route('ai-bots.provider-keys.index')
            ->with('status', 'Provider key deleted successfully.');
    }

    public function validateKey(AiProviderKey $provider_key): RedirectResponse
    {
        $isValid = $this->keyService->validateKey($provider_key);

        $status = $isValid ? 'validated successfully' : 'validation failed. Check your API key.';

        return redirect()
            ->route('ai-bots.provider-keys.index')
            ->with('status', "Provider key {$status}");
    }
}
