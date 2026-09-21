<?php

declare(strict_types=1);

use App\Modules\Authentication\Support\AuthKitUserDeletion;
use WorkOS\Client;
use WorkOS\Exception\BadRequestException;
use WorkOS\Exception\ServerException;
use WorkOS\RequestClient\CurlRequestClient;
use WorkOS\RequestClient\RequestClientInterface;

/**
 * Unit tests for \App\Modules\Authentication\Support\AuthKitUserDeletion.
 */

/**
 * Record the requests the WorkOS SDK issues and answer them with
 * a canned status, so no call leaves the test. The SDK uses its
 * own HTTP client, which the Laravel fake does not intercept.
 */
function fakeWorkOsRequestClient(int $status = 200, string $body = '{}'): RequestClientInterface
{
    $requestClient = new class($status, $body) implements RequestClientInterface
    {
        /**
         * The requests issued so far, in order.
         *
         * @var array<int, array{method: string, url: string}>
         */
        public array $requests = [];

        public function __construct(
            protected int $status,
            protected string $body
        ) {}

        /**
         * @param  array<int, string>|null  $headers
         * @param  array<string, mixed>|null  $params
         * @return array{0: string, 1: array<string, string>, 2: int}
         */
        public function request($method, $url, ?array $headers = null, ?array $params = null): array
        {
            $this->requests[] = ['method' => $method, 'url' => $url];

            return [$this->body, [], $this->status];
        }
    };

    Client::setRequestClient($requestClient);

    return $requestClient;
}

afterEach(function (): void {
    Client::setRequestClient(new CurlRequestClient);
});

it('deletes the user at workos', function (): void {
    $requestClient = fakeWorkOsRequestClient();

    new AuthKitUserDeletion()->deleteUsing('user_123');

    expect($requestClient->requests)->toHaveCount(1)
        ->and($requestClient->requests[0]['method'])->toBe(Client::METHOD_DELETE)
        ->and($requestClient->requests[0]['url'])->toContain('user_management/users/user_123');
});

it('treats a user workos does not hold as already deleted', function (): void {
    $requestClient = fakeWorkOsRequestClient(404, '{"code":"entity_not_found"}');

    new AuthKitUserDeletion()->deleteUsing('user_gone');

    expect($requestClient->requests)->toHaveCount(1);
});

it('propagates a refusal from workos', function (): void {
    fakeWorkOsRequestClient(400, '{"code":"invalid_request"}');

    expect(fn (): null => new AuthKitUserDeletion()->deleteUsing('user_123'))
        ->toThrow(BadRequestException::class);
});

it('propagates a workos server error', function (): void {
    fakeWorkOsRequestClient(500, '{"code":"internal_error"}');

    expect(fn (): null => new AuthKitUserDeletion()->deleteUsing('user_123'))
        ->toThrow(ServerException::class);
});
