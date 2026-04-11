<?php

namespace App\Controller\user;

use App\Entity\User;
use App\Entity\Preference;
use App\service\AuthService;
use App\service\PreferenceService;
use App\form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\FormFactoryInterface;

class AuthController extends AbstractController
{
    private AuthService $authService;
    private Security $security;
    private PreferenceService $preferenceService;

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['form.factory'] = '?'.FormFactoryInterface::class;
        return $services;
    }

    public function setAuthService(AuthService $authService): void
    {
        $this->authService = $authService;
    }

    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    public function setPreferenceService(PreferenceService $preferenceService): void
    {
        $this->preferenceService = $preferenceService;
    }

    /* ── LOGIN PAGE with brute force protection ── */
    #[Route('/', name: 'app_login')]
    #[Route('/login')]
    public function login(Request $request, AuthenticationUtils $authUtils, EntityManagerInterface $em): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('index');
        }

        $lastUsername = $authUtils->getLastUsername();

        $registrationForm = $this->createForm(RegistrationFormType::class, new User());
        $session = $request->getSession();

        return $this->render('front/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),
            'form' => $registrationForm->createView(),
            'lock_until' => $session->get('locked_until'),
            'error_type' => $session->get('login_error_type'),
        ]);
    }

    /* ── LOGOUT ── */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method should never be reached.');
    }

    /* ── REGISTER with password strength validation ── */
    
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $user->getPlainPassword();

        // Extra password strength check (uppercase, lowercase)
        $errors = [];
            if (strlen($plainPassword) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if (!preg_match('/[A-Z]/', $plainPassword)) {
                $errors[] = 'Password must contain at least 1 uppercase letter.';
            }
            if (!preg_match('/[a-z]/', $plainPassword)) {
                $errors[] = 'Password must contain at least 1 lowercase letter.';
            }
            if (!preg_match('/[0-9]/', $plainPassword)) {
                $errors[] = 'Password must contain at least 1 number.';
            }
        
            if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('signup_error', $error);
            }
            return $this->redirectToRoute('app_login', ['signup' => 1]);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setRole('user');
        $user->setStatus('active');
        $user->setEmailVerified(true);

        $avatarService = new \App\service\AvatarService();
        $user->setAvatarId($user->getUserId());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Account created successfully! Welcome to TripX.');
        $request->getSession()->set('onboarding_user_id', $user->getUserId());

        return $this->redirectToRoute('app_onboarding');
    }

    // Collect all form errors
    foreach ($form->getErrors(true) as $error) {
        $this->addFlash('signup_error', $error->getMessage());
    }

    return $this->redirectToRoute('app_login', ['signup' => 1]);
}

    /* ── ONBOARDING PAGE with session persistence ── */
    #[Route('/onboarding', name: 'app_onboarding')]
    public function onboarding(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('onboarding_user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $existingPrefs = $em->getRepository(Preference::class)->findOneBy(['userId' => $userId]);
        if ($existingPrefs && $existingPrefs->getTravelPace()) {
            $request->getSession()->remove('onboarding_user_id');
            return $this->redirectToRoute('index');
        }

        return $this->render('front/onboarding.html.twig');
    }

    /* ── SAVE PREFERENCES ── */
    #[Route('/preferences/save', name: 'app_save_preferences', methods: ['POST'])]
    public function savePreferences(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $userId = $request->getSession()->get('onboarding_user_id');

        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Session expired, please login again'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if ($this->preferenceService->savePreferences((int) $userId, $data)) {
            $user = $em->getRepository(User::class)->find($userId);
            if ($user) {
                $this->security->login($user);
            }
            $request->getSession()->remove('onboarding_user_id');
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Saving failed'], 500);
    }
}