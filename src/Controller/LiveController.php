<?php

namespace App\Controller;

use App\Entity\Stage;
use App\Entity\Trip;
use App\Security\Voter\UserVoter;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/trip/{trip}/live', name: 'live_')]
class LiveController extends MappableController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(Trip $trip): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $stage = $this->stageRepository->findFirstStage($trip)
            ?? throw $this->createNotFoundException('No stage for this Trip');

        return $this->redirectToRoute('live_show_stage', [
            'trip' => $trip->getId(),
            'stage' => $stage->getId(),
        ]);
    }

    /** @return array<mixed> */
    #[Route('/{stage}', name: 'show_stage', methods: ['GET'])]
    #[Template('live/index.html.twig')]
    public function showStage(Trip $trip, Stage $stage = null): array
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $stage);

        $interests = $this->interestRepository->findByTrip($trip);
        [$results, $stages, $routings, $extras, $totalDistance] = $this->tripService->calculateResults($trip);

        return [
            'urls' => $this->getUrls($trip),
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip),
            'trip' => $trip,
            'results' => $results,
            'stages' => $stages,
            'stage' => $stage,
            'routings' => $routings,
            'extras' => $extras,
            'interests' => $interests,
            'total_distance' => $totalDistance,
            'segments' => $trip->getSegments(),
        ];
    }

    #[Route('/js/live.js', name: 'show_js', methods: ['GET'])]
    public function js(
        Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false
    ): Response {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);

        $response = $this->render(
            'live/index.js.twig',
            array_merge($this->showStage($trip), ['first_load' => $firstLoad])
        );
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }
}
