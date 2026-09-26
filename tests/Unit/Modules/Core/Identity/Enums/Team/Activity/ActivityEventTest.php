<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Enums\Team\Activity\ActivityEvent;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

/**
 * Unit tests for \App\Modules\Core\Identity\Enums\Team\Activity\ActivityEvent.
 */
it('names a stored event in the timeline\'s language', function (string $locale, string $event, string $label): void {
    App::setLocale($locale);

    expect(ActivityEvent::labelFor($event))->toBe($label);
})->with([
    'a tag change in english' => ['en', 'tags_synced', 'Tags changed'],
    'a tag change in spanish' => ['es', 'tags_synced', 'Etiquetas cambiadas'],
    'a creation in spanish' => ['es', 'created', 'Creado'],
    'a role change in spanish' => ['es', 'member_role_updated', 'Rol cambiado'],
]);

it('falls back to the stored name for an event it does not know', function (): void {
    expect(ActivityEvent::labelFor('something_new'))->toBe('something_new');
});

it('labels every event the app records', function (): void {
    $recorded = collect(File::allFiles(app_path()))
        ->flatMap(fn (SplFileInfo $file): array => preg_match_all("/TeamActivity::record\\([^,]+,\\s*'([a-z_]+)'/", File::get($file->getPathname()), $matches) ? $matches[1] : [])
        ->unique()
        ->values();

    expect($recorded)->not->toBeEmpty()
        ->each(fn ($event) => $event->toBeIn(array_column(ActivityEvent::cases(), 'value')));
});
