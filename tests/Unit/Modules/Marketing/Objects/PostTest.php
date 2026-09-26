<?php

declare(strict_types=1);

use App\Modules\Marketing\Objects\Post;

/**
 * Unit tests for \App\Modules\Marketing\Objects\Post.
 */
it('refuses a post without a published date', function (): void {
    Post::parse('en', 'undated', "---\ntitle: \"Undated\"\n---\n\nBody.\n");
})->throws(InvalidArgumentException::class, 'The blog post [en/undated] has no published date.');

it('reads the date, image, draft flag and translations from the front matter', function (): void {
    $post = Post::parse('en', 'hello', <<<'MARKDOWN'
        ---
        title: "Hello"
        published: "2026-09-01"
        updated: "2026-09-10"
        image: "/images/blog/hello.png"
        draft: true
        translations: {"es": "hola"}
        ---

        Body.
        MARKDOWN);

    expect($post->published->toDateString())->toBe('2026-09-01')
        ->and($post->updated?->toDateString())->toBe('2026-09-10')
        ->and($post->image)->toBe('/images/blog/hello.png')
        ->and($post->draft)->toBeTrue()
        ->and($post->translations)->toBe(['es' => 'hola']);
});

it('works out the reading time at two hundred words a minute, never under one', function (string $body, int $minutes): void {
    $post = Post::parse('en', 'timed', "---\npublished: \"2026-09-01\"\n---\n\n{$body}\n");

    expect($post->minutes())->toBe($minutes);
})->with([
    'a few words' => ['Just a few words.', 1],
    'exactly two hundred words' => [str_repeat('word ', 200), 1],
    'one word over two hundred' => [str_repeat('word ', 201), 2],
]);
