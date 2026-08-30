<?php

namespace App\Modules\Support\Objects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\ResponseFactory;

class Toast implements Arrayable
{
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
            'id' => $this->id,
            'message' => $this->message,
        ];
    }
}
