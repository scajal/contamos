<?php

namespace App\Modules\Authentication\Http\Controllers;

use App\Modules\Authentication\Actions\ResolveAuthenticationRedirect;
use App\Modules\Authentication\Support\AuthKitAuthentication;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController
{
    /**
     * Redirect the user to the WorkOS
     * authentication flow.
     */
    public function login(AuthKitLoginRequest $request): Response
    {
        return $request->redirect();
    }

    /**
     * Handle the callback from the WorkOS
     * authentication flow.
     */
    public function authenticate(AuthKitAuthenticationRequest $request, AuthKitAuthentication $authKit, ResolveAuthenticationRedirect $resolveAuthenticationRedirect): Response
    {
        $request->authenticate($authKit->findUsing(...), $authKit->createUsing(...));

        dd($resolveAuthenticationRedirect->fromRequest($request));

        return redirect()->to($resolveAuthenticationRedirect->fromRequest($request));
    }

    /**
     * Logout the user from the app and from WorkOS.
     */
    public function logout(AuthKitLogoutRequest $request): Response
    {
        return $request->logout('/');
    }
}
