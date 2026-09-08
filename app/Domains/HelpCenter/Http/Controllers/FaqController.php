<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Http\Controllers;

use App\Domains\HelpCenter\Services\FaqQueryService;
use App\Domains\HelpCenter\Support\FaqPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(
        Request $request,
        FaqQueryService $queryService,
        FaqPresenter $presenter,
        ?string $slug = null,
    ): View {
        $search = $request->string('q')->trim()->toString();
        $faqs = $queryService->active($search !== '' ? $search : null);
        $activeSlug = $slug ?? $request->string('slug')->trim()->toString() ?: null;

        if ($activeSlug !== null && ! $faqs->contains('slug', $activeSlug)) {
            $matched = $queryService->findActiveBySlug($activeSlug);

            if ($matched !== null && $search === '') {
                $faqs = $queryService->active();
            }
        }

        return view('faqs.index', [
            'faqs' => $presenter->accordionRows($faqs, $activeSlug),
            'search' => $search,
            'activeSlug' => $activeSlug,
        ]);
    }
}
