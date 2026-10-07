<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Marketing\Enums\Compare\Competitor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class CompareController
{
    /**
     * Show how Contamos compares with another product, for
     * a reader who already uses that product and is looking
     * for an alternative.
     */
    public function show(string $locale, Competitor $competitor, #[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        $locale = App::currentLocale();

        return Inertia::render('marketing/compare/show', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            // The homepage in the reader's language, so the shell's
            // wordmark and section links lead out of the page.
            'homeUrl' => route('home', ['locale' => $locale]),
            'alternates' => [
                'en' => $competitor->url('en'),
                'es' => $competitor->url('es'),
                'default' => $competitor->url('en'),
            ],
            'competitor' => $competitor->value,
            'name' => $competitor->getLabel(),
            'document' => $competitor->document($locale),
            'markdownUrl' => $competitor->markdownUrl($locale),
            'imageUrl' => url('/images/og.png'),
            // Every other comparison, so a reader who came for
            // one product finds the rest.
            'others' => collect(Competitor::cases())
                ->reject(fn (Competitor $other): bool => $other === $competitor)
                ->map(fn (Competitor $other): array => [
                    'name' => $other->getLabel(),
                    'url' => $other->url($locale),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Serve a comparison as Markdown, for a reader such as
     * an AI assistant that has no use for the page around it.
     */
    public function markdown(string $locale, Competitor $competitor): HttpResponse
    {
        return response(
            $competitor->markdown($locale),
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }
}
