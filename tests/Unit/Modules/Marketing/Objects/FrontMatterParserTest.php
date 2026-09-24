<?php

declare(strict_types=1);

use App\Modules\Marketing\Objects\FrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;

/**
 * Unit tests for \App\Modules\Marketing\Objects\FrontMatterParser.
 */
it('reads quoted strings and booleans and keeps bare text', function (): void {
    expect(new FrontMatterParser()->parse("---\ntitle: \"Terms\"\n\npro: true\nupdated: 2026-09-01\n---"))->toBe([
        'title' => 'Terms',
        'pro' => true,
        'updated' => '2026-09-01',
    ]);
});

it('refuses a line that is not a key and its value', function (string $line): void {
    new FrontMatterParser()->parse("title: \"Terms\"\n{$line}");
})->with([
    'no colon' => ['just some text'],
    'a list item' => ['- item'],
    'a key starting with a digit' => ['1title: "Terms"'],
])->throws(InvalidFrontMatterException::class, 'Invalid front matter line:');
