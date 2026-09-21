<?php

namespace App\Modules\Support\Objects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\ResponseFactory;

/**
 * @implements \Illuminate\Contracts\Support\Arrayable<string, mixed>
 */
class Toast implements Arrayable
{
    /**
     * The one thing the toast offers to do about what just happened,
     * such as undoing it.
     *
     * @var array{label: string, method: string, url: string}|null
     */
    protected ?array $action = null;

    /**
     * Create a new instance of the class.
     */
    public function __construct(
        public string $message,
        public ?string $id = null
    ) {
        $this->id = Str::ulid()->toString();
    }

    /**
     * Offer a single action alongside the message. A write the user
     * can take back says so here rather than behind a confirmation
     * they would have had to read before acting.
     */
    public function action(string $label, string $url, string $method = 'delete'): self
    {
        $this->action = [
            'label' => $label,
            'method' => $method,
            'url' => $url,
        ];

        return $this;
    }

    /**
     * Flash the toast to the inertia's session.
     */
    public function flash(): ResponseFactory
    {
        return Inertia::flash('toast', $this->toArray());
    }

    /**
     * Create a new instance of the class and
     * set the message.
     */
    public static function make(string $message): self
    {
        return new self($message);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'id' => $this->id,
            'message' => $this->message,
        ];
    }
}
