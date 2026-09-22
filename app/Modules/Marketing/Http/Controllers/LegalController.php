<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class LegalController
{
    /**
     * Show the privacy policy.
     */
    public function privacy(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return $this->document('privacy', $user, $redirect);
    }

    /**
     * Show the refund policy.
     */
    public function refunds(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return $this->document('refunds', $user, $redirect);
    }

    /**
     * Show the terms of service.
     */
    public function terms(#[CurrentUser] ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return $this->document('terms', $user, $redirect);
    }

    /**
     * Render one of the legal documents, which differ only in
     * their copy: the shell and the locale alternates are the
     * same on all three.
     */
    protected function document(string $name, ?User $user, ResolveAuthenticationRedirect $redirect): Response
    {
        return Inertia::render("marketing/legal/{$name}", [
            'dashboardUrl' => $user instanceof User ? $redirect->forUser($user) : null,
            // The homepage in the reader's language, so the shell's
            // wordmark leads out of the document instead of back to it.
            'homeUrl' => route('home', ['locale' => App::currentLocale()]),
            'alternates' => [
                'en' => route("legal.{$name}", ['locale' => 'en']),
                'es' => route("legal.{$name}", ['locale' => 'es']),
                'default' => route("legal.{$name}", ['locale' => 'en']),
            ],
        ]);
    }
}
