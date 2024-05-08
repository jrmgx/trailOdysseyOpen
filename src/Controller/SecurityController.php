<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /** @return Response|array<mixed> */
    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    #[Template('home/login.html.twig')]
    public function login(AuthenticationUtils $authenticationUtils): Response|array
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('trip_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return ['last_username' => $lastUsername, 'error' => $error];
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
