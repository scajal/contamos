<?php

declare(strict_types=1);

use App\Modules\Authentication\Support\AuthKitAuthorizedApplications;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Unit tests for \App\Modules\Authentication\Support\AuthKitAuthorizedApplications.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('leaves out entries that name no application to revoke', function (): void {
    Http::fake([
        'api.workos.com/user_management/users/user_123/authorized_applications*' => Http::response([
            'data' => [
                ['id' => 'authorized_connect_app_01'],
                ['id' => 'authorized_connect_app_02', 'application' => 'conn_app_claude'],
                ['id' => 'authorized_connect_app_03', 'application' => ['name' => 'Nameless']],
                ['id' => 'authorized_connect_app_04', 'application' => ['id' => '', 'name' => 'Blank']],
                ['id' => 'authorized_connect_app_05', 'application' => ['id' => 'conn_app_chatgpt', 'name' => 'ChatGPT', 'client_id' => 'chatgpt']],
            ],
            'list_metadata' => ['before' => null, 'after' => null],
        ]),
    ]);

    expect(new AuthKitAuthorizedApplications()->listFor('user_123'))->toBe([
        ['id' => 'conn_app_chatgpt', 'name' => 'ChatGPT', 'client_id' => 'chatgpt'],
    ]);
});

it('treats an application workos no longer holds as already revoked', function (): void {
    Http::fake([
        'api.workos.com/user_management/users/user_123/authorized_applications/conn_app_gone' => Http::response(['code' => 'entity_not_found'], 404),
    ]);

    new AuthKitAuthorizedApplications()->revokeFor('user_123', 'conn_app_gone');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://api.workos.com/user_management/users/user_123/authorized_applications/conn_app_gone');
});

it('propagates any other refusal from workos', function (): void {
    Http::fake([
        'api.workos.com/user_management/users/user_123/authorized_applications/conn_app_claude' => Http::response(['message' => 'Unavailable'], 503),
    ]);

    expect(fn () => new AuthKitAuthorizedApplications()->revokeFor('user_123', 'conn_app_claude'))
        ->toThrow(RequestException::class);
});
