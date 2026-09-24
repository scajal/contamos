<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Marketing\Enums\Docs\Group;
use App\Modules\Marketing\Enums\Docs\Page;
use App\Modules\Mcp\Helpers\AssistantGuide;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class DocsController
{
    /**
     * Show the introduction, which is the index of the
     * docs.
     */
    public function index(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return $this->page(Page::Introduction, $user, $redirect);
    }

    /**
     * Show a page of the docs.
     */
    public function show(string $locale, Page $page, #[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return $this->page($page, $user, $redirect);
    }

    /**
     * Serve a page of the docs as Markdown, for a reader
     * such as an AI assistant that has no use for the page
     * around it.
     */
    public function markdown(string $locale, Page $page): HttpResponse
    {
        return response(
            $page->markdown($locale),
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }

    /**
     * Serve llms-full.txt: every docs page in English, in
     * the order of the sidebar, joined into one text an AI
     * assistant can take in with a single request. The legal
     * documents are left out.
     */
    public function full(): HttpResponse
    {
        $pages = collect(Page::cases())
            ->map(fn (Page $page): string => $page->markdown('en'))
            ->implode("\n---\n\n");

        return response(
            $pages,
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    /**
     * Render a page of the docs inside the marketing shell,
     * with the sidebar that lists every page.
     */
    protected function page(Page $page, ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        $locale = App::currentLocale();

        return Inertia::render('marketing/docs/show', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            // The homepage in the reader's language, so the shell's
            // wordmark and section links lead out of the docs.
            'homeUrl' => route('home', ['locale' => $locale]),
            'alternates' => [
                'en' => $page->url('en'),
                'es' => $page->url('es'),
                'default' => $page->url('en'),
            ],
            'page' => $page->value,
            'group' => $page->group()->getLabel(),
            'document' => $page->document($locale),
            'markdownUrl' => $page->markdownUrl($locale),
            // The AI assistants page shows the same connection
            // steps and example prompts as the app's own page.
            'assistants' => $page === Page::AiAssistants ? AssistantGuide::toArray() : null,
            // Every group with its pages, in sidebar order. Whether
            // a page is Pro is read from its own front matter, so
            // the badge is written in one place.
            'navigation' => collect(Group::cases())
                ->filter(fn (Group $group): bool => $group->pages() !== [])
                ->values()
                ->map(fn (Group $group): array => [
                    'label' => $group->getLabel(),
                    'pages' => collect($group->pages())
                        ->map(fn (Page $item): array => [
                            'page' => $item->value,
                            'label' => $item->getLabel(),
                            'pro' => $item->document($locale)->pro,
                            'url' => $item->url($locale),
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }
}
