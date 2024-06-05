<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Trip;
use App\Entity\User;
use App\Helper\CommonHelper;
use App\Repository\BagRepository;
use App\Repository\DiaryEntryRepository;
use App\Service\TripService;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/public', name: 'public_')]
class PublicController extends BaseController
{
    public function __construct(
        private readonly FilterService $filterService,
        private readonly DiaryEntryRepository $diaryEntryRepository,
        private readonly TripService $tripService,
        private readonly BagRepository $bagRepository,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    /** @return array<mixed> */
    #[Route('/trip/{trip}', name: 'index', methods: ['GET'])]
    #[Template('public/index.html.twig')]
    public function index(#[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip): array
    {
        if (!$trip->isShared()) {
            throw $this->createNotFoundException();
        }

        $diaryEntries = $this->diaryEntryRepository->findByTrip($trip);
        $routings = $this->tripService->calculateRoutings($trip);
        // TODO/BUG: routing/distance should only count the progress part of the trip!?
        [$sumDistance, $sumPositive, $sumNegative] = $this->tripService->calculateSums($trip);

        // Bags
        $user = $trip->getUser();
        $bags = $this->bagRepository->findBagsForTripAndUser($trip, $user);

        return [
            'public' => true,
            'urls' => (string) json_encode([]),
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip, true),
            'trip' => $trip,
            'diaryEntries' => $diaryEntries,
            'routings' => $routings,
            'sum_distance' => $sumDistance,
            'sum_positive' => $sumPositive,
            'sum_negative' => $sumNegative,
            'bags' => $bags,
        ];
    }

    #[Route('/trip/{trip}/js/index.js', name: 'index_js', methods: ['GET'])]
    public function js(
        #[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false
    ): Response {
        $response = $this->render('public/index.js.twig', array_merge($this->index($trip), ['first_load' => $firstLoad]));
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }

    #[Route('/photo/{user}/{trip}/{path}', name: 'photo', methods: ['GET'])]
    public function photo(User $user, Trip $trip, Photo $photo): Response
    {
        CommonHelper::allowMoreResources();

        // Either the user should be the one owning the trip or the trip public
        if (!$trip->isShared() && $this->getUser() !== $trip->getUser()) {
            throw $this->createNotFoundException();
        }

        $url = '/uploads/' . $user->getId() . '/' . $photo->getPath();
        $resolved = $this->filterService->getUrlOfFilteredImage($url, 'interest_image');

        return $this->redirect($resolved);
    }
}
