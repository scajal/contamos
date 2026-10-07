<?php

declare(strict_types=1);

use App\Modules\Marketing\Enums\Compare\Competitor;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

/**
 * Feature tests for \App\Modules\Marketing\Http\Controllers\CompareController.
 */

/**
 * Every comparison in both locales, so a comparison whose
 * Markdown is missing in either language fails the suite.
 *
 * @return array<string, array{0: \App\Modules\Marketing\Enums\Compare\Competitor, 1: string}>
 */
function comparisons(): array
{
    $comparisons = [];

    foreach (Competitor::cases() as $competitor) {
        foreach (['en', 'es'] as $locale) {
            $comparisons["{$competitor->value} ({$locale})"] = [$competitor, $locale];
        }
    }

    return $comparisons;
}

describe('show', function (): void {
    it('renders the comparison from its markdown', function (Competitor $competitor, string $locale): void {
        $markdown = file_get_contents(resource_path("markdown/compare/{$locale}/{$competitor->value}.md")) ?: '';

        // The title as the front matter spells it.
        preg_match('/^title: "(.+)"$/m', $markdown, $title);

        get(route('compare.show', ['locale' => $locale, 'competitor' => $competitor->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('marketing/compare/show')
                ->where('locale', $locale)
                ->where('competitor', $competitor->value)
                ->where('name', $competitor->getLabel())
                ->where('document.title', $title[1])
                ->where('document.description', fn (string $description): bool => $description !== '')
                ->where('document.updated', fn (string $updated): bool => $updated !== '')
                ->where('document.html', fn (string $html): bool => str_contains($html, '<table>'))
                ->has('document.headings')
                ->where('markdownUrl', route('compare.markdown', ['locale' => $locale, 'competitor' => $competitor->value]))
                ->where('alternates.en', $competitor->url('en'))
                ->where('alternates.es', $competitor->url('es'))
                ->where('alternates.default', $competitor->url('en'))
                ->where('homeUrl', route('home', ['locale' => $locale]))
                ->where('dashboardUrl', null));
    })->with(comparisons());

    it('links every other comparison in the same language', function (): void {
        get(route('compare.show', ['locale' => 'es', 'competitor' => Competitor::Splitwise->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->count('others', count(Competitor::cases()) - 1)
                ->where('others.0.name', Competitor::Tricount->getLabel())
                ->where('others.0.url', Competitor::Tricount->url('es')));
    });

    it('points a signed-in reader at the dashboard', function (): void {
        [, $team] = actingMember();

        get(route('compare.show', ['locale' => 'en', 'competitor' => Competitor::Splitwise->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('dashboardUrl', fn (string $url): bool => str_contains($url, $team->slug)));
    });

    it('404s for a product it is not compared with', function (): void {
        get('/en/compare/ynab')
            ->assertNotFound();
    });

    it('404s for an unsupported locale', function (): void {
        get('/fr/compare/'.Competitor::Splitwise->value)
            ->assertNotFound();
    });
});

describe('markdown', function (): void {
    it('serves the comparison as markdown', function (Competitor $competitor, string $locale): void {
        $response = get(route('compare.markdown', ['locale' => $locale, 'competitor' => $competitor->value]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        expect($response->getContent())
            ->toStartWith('# '.$competitor->document($locale)->title."\n")
            ->not->toContain('description:')
            ->not->toContain('](/');
    })->with(comparisons());

    it('404s for a product it is not compared with', function (): void {
        get('/en/compare/ynab.md')
            ->assertNotFound();
    });
});
