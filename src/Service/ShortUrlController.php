<?php

namespace App\Service;

use App\Entity\Trip;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'short_')]
class ShortUrlController extends AbstractController
{
    #[Route('/{user}', name: 'index', requirements: ['user' => '^[a-z0-9_\.-]{3,}$'], methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user
    ): Response {
        return $this->redirectToRoute('public_index', [
            'user' => $user->getNickname(),
        ]);
    }

    #[Route('/{user}/{trip}', name: 'show', requirements: ['user' => '^[a-z0-9_\.-]{3,}$'], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user,
        #[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip
    ): Response {
        if (!$trip->isShared() || $trip->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('public_show', [
            'user' => $user->getNickname(),
            'trip' => $trip->getShareKey(),
        ]);
    }
}
