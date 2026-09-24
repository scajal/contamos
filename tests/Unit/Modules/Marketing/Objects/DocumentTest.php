<?php

declare(strict_types=1);

use App\Modules\Marketing\Objects\Document;

/**
 * Unit tests for \App\Modules\Marketing\Objects\Document.
 */
it('reads a file without front matter as an untitled page', function (): void {
    $document = Document::parse("Just a paragraph.\n");

    expect($document->title)->toBe('')
        ->and($document->description)->toBe('')
        ->and($document->pro)->toBeFalse()
        ->and($document->updated)->toBeNull()
        ->and($document->body)->toBe('Just a paragraph.')
        ->and($document->html)->toContain('<p>Just a paragraph.</p>');
});

it('lists only second and third level headings in the table of contents', function (): void {
    $document = Document::parse(<<<'MARKDOWN'
        ---
        title: "Guide"
        ---
        # Guide

        ## Getting started

        ### Install

        #### Requirements
        MARKDOWN);

    expect($document->headings)->toBe([
        ['id' => 'getting-started', 'text' => 'Getting started', 'level' => 2],
        ['id' => 'install', 'text' => 'Install', 'level' => 3],
    ]);
});
