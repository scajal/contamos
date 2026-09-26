<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Marketing\Objects\Post;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class BlogController
{
    /**
     * Show the blog: every published post in the reader's
     * language, newest first.
     */
    public function index(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        $locale = App::currentLocale();

        return Inertia::render('marketing/blog/index', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            // The homepage in the reader's language, so the shell's
            // wordmark and section links lead out of the blog.
            'homeUrl' => route('home', ['locale' => $locale]),
            'alternates' => [
                'en' => route('blog.index', ['locale' => 'en']),
                'es' => route('blog.index', ['locale' => 'es']),
                'default' => route('blog.index', ['locale' => 'en']),
            ],
            'feedUrl' => route('blog.feed', ['locale' => $locale]),
            'imageUrl' => url('/images/og.png'),
            'posts' => Post::all($locale)
                ->map(fn (Post $post): array => $post->summary())
                ->all(),
        ]);
    }

    /**
     * Show a post of the blog.
     */
    public function show(string $locale, string $slug, #[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        $post = Post::find($locale, $slug);

        return Inertia::render('marketing/blog/show', [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            'homeUrl' => route('home', ['locale' => $locale]),
            // Where the language switcher leads, which is the blog
            // itself in a language the post was not written in.
            'alternates' => $post->alternates(),
            // The languages the post can actually be read in,
            // which are the only ones a crawler is told about.
            'versions' => $post->versions(),
            'blogUrl' => route('blog.index', ['locale' => $locale]),
            'feedUrl' => route('blog.feed', ['locale' => $locale]),
            'markdownUrl' => $post->markdownUrl(),
            'logoUrl' => url('/images/iso-logo.png'),
            'post' => $post,
        ]);
    }

    /**
     * Serve a post as Markdown, for a reader such as an AI
     * assistant that has no use for the page around it.
     */
    public function markdown(string $locale, string $slug): HttpResponse
    {
        return response(
            Post::find($locale, $slug)->markdown(),
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }

    /**
     * Serve the blog in the given language as an RSS feed,
     * for feed readers and the aggregators that pick new
     * posts up from it.
     */
    public function feed(string $locale): HttpResponse
    {
        $posts = Post::all($locale);

        // One item per post, newest first, described by the
        // summary its front matter gives.
        $items = $posts
            ->map(fn (Post $post): string => '<item>'
                .'<title>'.e($post->document->title).'</title>'
                .'<link>'.e($post->url()).'</link>'
                .'<guid isPermaLink="true">'.e($post->url()).'</guid>'
                .'<description>'.e($post->document->description).'</description>'
                .'<pubDate>'.$post->published->toRfc2822String().'</pubDate>'
                .'</item>')
            ->implode('');

        // The channel is as recent as its newest post.
        $built = $posts->first()?->published->toRfc2822String();

        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            .'<title>'.e(trans('Contamos blog')).'</title>'
            .'<link>'.e(route('blog.index', ['locale' => $locale])).'</link>'
            .'<atom:link href="'.e(route('blog.feed', ['locale' => $locale])).'" rel="self" type="application/rss+xml"/>'
            .'<description>'.e(trans('Notes on keeping track of money with the people you share it with, in every currency it comes in.')).'</description>'
            .'<language>'.e($locale).'</language>'
            .($built !== null ? '<lastBuildDate>'.$built.'</lastBuildDate>' : '')
            .$items
            .'</channel></rss>',
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
        );
    }
}
