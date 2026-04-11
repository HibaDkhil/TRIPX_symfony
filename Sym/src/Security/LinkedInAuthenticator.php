<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class LinkedInAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/connect/linkedin/check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('linkedin');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken) {
                /** @var \League\OAuth2\Client\Provider\LinkedInResourceOwner $linkedInUser */
                $linkedInUser = $this->clientRegistry->getClient('linkedin')->fetchUserFromToken($accessToken);
                
                $email = $linkedInUser->getEmail() ?? $linkedInUser->getEmail();
                
                if (!$email) {
                    throw new \Exception('No email provided by LinkedIn');
                }
                
                // Check if user exists
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($linkedInUser->getFirstName() ?? 'Professional');
                    $user->setLastName($linkedInUser->getLastName() ?? '');
                    // Secure placeholder password for social users
                    $user->setPassword(bin2hex(random_bytes(24)));
                    $user->setRole('user');
                    $user->setStatus('active');
                    $user->setEmailVerified(true);
                    
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }
                
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $exception = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}