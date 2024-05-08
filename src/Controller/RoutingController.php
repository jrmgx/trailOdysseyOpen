<?php

namespace App\Controller;

use App\Entity\Routing;
use App\Entity\Trip;
use App\Security\Voter\UserVoter;
use App\Service\RoutingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/trip/{trip}/routing', name: 'routing_')]
class RoutingController extends AbstractController
{
    public function __construct(
        private readonly RoutingService $routingService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::VIEW, 'trip')]
    #[IsGranted(UserVoter::EDIT, 'routing')]
    #[Template('routing/edit_frame.html.twig')]
    public function edit(Trip $trip, Routing $routing): Response|array
    {
        return [
            'trip' => $trip,
            'routing' => $routing,
            'parent_url' => $this->generateUrl('stage_show', ['trip' => $trip->getId()]),
        ];
    }

    #[Route('/{id}/update_path', name: 'update_path', methods: ['POST'])]
    public function updatePath(Request $request, Trip $trip, Routing $routing): Response
    {
        // TODO transform to security attributes
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $routing);

        if ($this->isCsrfTokenValid('update_path' . $routing->getId(), (string) $request->request->get('_token'))) {
            $trip->updatedNow();
            $this->routingService->updatePathPoints($trip, $routing);
            $this->routingService->updateCalculatedValues($routing);
            $this->entityManager->persist($routing);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/clear_path', name: 'clear_path', methods: ['POST'])]
    public function removePath(Request $request, Trip $trip, Routing $routing): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $routing);

        if ($this->isCsrfTokenValid('clear_path' . $routing->getId(), (string) $request->request->get('_token'))) {
            $trip->updatedNow();
            $routing->setPathPoints(null);
            $routing->setAsTheCrowFly(true);
            $this->routingService->updateCalculatedValues($routing);
            $this->entityManager->persist($routing);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }
}
