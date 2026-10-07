<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Enums\Compare;

use App\Modules\Marketing\Objects\Document;
use Filament\Support\Contracts\HasLabel;

/**
 * A product Contamos is compared with on a page of its
 * own, named by its slug, which is shared by every locale.
 * Adding a comparison is adding a case here and its
 * Markdown file in each locale; the sitemap and the footer
 * follow from the cases.
 */
enum Competitor: string implements HasLabel
{
    /**
     * The bill-splitting app most groups start with.
     */
    case Splitwise = 'splitwise';

    /**
     * The free bill splitter owned by bunq.
     */
    case Tricount = 'tricount';

    /**
     * Get the comparison's document in the given locale.
     */
    public function document(string $locale): Document
    {
        return Document::find('compare', $this->value, $locale);
    }

    /**
     * Get the comparison as Markdown in the given locale.
     */
    public function markdown(string $locale): string
    {
        return $this->document($locale)->markdown();
    }

    /**
     * Get the URL of the comparison in the given locale.
     */
    public function url(string $locale): string
    {
        return route('compare.show', ['locale' => $locale, 'competitor' => $this->value]);
    }

    /**
     * Get the URL of the comparison's Markdown in the given
     * locale.
     */
    public function markdownUrl(string $locale): string
    {
        return route('compare.markdown', ['locale' => $locale, 'competitor' => $this->value]);
    }

    /**
     * Get the name of the product, which is a proper noun
     * and reads the same in every language.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Splitwise => 'Splitwise',
            self::Tricount => 'Tricount',
        };
    }
}
