<?php

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AppBackendAuthenticator extends AbstractAuthenticator
{

    public const LOGIN_ROUTE = 'app_login';

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        // Get the username and password from the request
        $username = $request->request->get('_username');
        $password = $request->request->get('_password');

        if (null === $username || '' === $username) {
            throw new CustomUserMessageAuthenticationException('Username is required.');
        }

        // You can also include CSRF token verification if needed
        $csrfToken = $request->request->get('_csrf_token');

        return new Passport(
            new UserBadge($username), // Make sure $username is not null
            new PasswordCredentials($password),
            [new CsrfTokenBadge('login_form', $csrfToken)]
        );
    }


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        //return new RedirectResponse('/dashboard');
        return new Response();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);

        //return new RedirectResponse('/login');
        return new Response();
    }
}
