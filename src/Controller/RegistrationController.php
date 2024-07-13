<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\Authenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly Authenticator $authenticator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly bool $instanceOpen,
    ) {
    }

    /** @return Response|array<mixed>|null */
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    #[Template('home/register.html.twig')]
    public function register(Request $request): Response|array|null
    {
        if (!$this->instanceOpen) {
            throw $this->createAccessDeniedException($this->translator->trans('info.registration_closed'));
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('trip_index');
        }

        $user = new User();
        $registrationForm = $this->createForm(RegistrationFormType::class, $user, [
            'action' => $this->generateUrl('register'),
        ]);
        $registrationForm->handleRequest($request);

        if ($registrationForm->isSubmitted() && $registrationForm->isValid()) {
            // encode the plain password
            $user->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $user,
                    $registrationForm->get('plainPassword')->getData()
                )
            );

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            // do anything else you need here, like send an email

            return $this->userAuthenticator->authenticateUser(
                $user,
                $this->authenticator,
                $request
            );
        }

        return compact('registrationForm');
    }
}
