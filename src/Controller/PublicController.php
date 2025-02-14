<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Trip;
use App\Entity\User;
use App\Helper\CommonHelper;
use App\Repository\BagRepository;
use App\Repository\DiaryEntryRepository;
use App\Repository\TripRepository;
use App\Service\TripService;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/public', name: 'public_')]
class PublicController extends BaseController
{
    private const MAX_PICTURE = 9;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly DiaryEntryRepository $diaryEntryRepository,
        private readonly TripService $tripService,
        private readonly BagRepository $bagRepository,
        private readonly TripRepository $tripRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    /** @return array<mixed> */
    #[Route('/{user}', name: 'index', methods: ['GET'])]
    #[Template('public/index.html.twig')]
    public function index(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user,
    ): array {
        $trips = $this->tripRepository->findPublicForUser($user);
        $userId = $user->getId();
        // We go through diaries to be sure that the photo is public (no in interest) and used (not removed)
        foreach ($trips as $trip) {
            $picturesForTrip = [];
            $tripId = $trip->getId();
            $diaries = $trip->getDiaryEntries();
            foreach ($diaries as $diary) {
                $matches = [];
                // ![](/1901/2910/74d4d6de71676855936e312ffd6f1cb75cf8118a.jpg)
                // https://regex101.com/r/j4DqZT/1
                /* @noinspection RegExpRedundantEscape */
                preg_match_all(
                    "`\]\(/$userId/$tripId/([a-z0-9]{40}\.[a-z]{3,5})\)`mi",
                    $diary->getDescription() ?? '',
                    $matches
                );
                foreach ($matches[1] as $match) {
                    $picturesForTrip[] = $this->urlGenerator->generate('public_photo_with_filter', [
                        'trip' => $tripId,
                        'user' => $userId,
                        'path' => $match,
                        'filter' => 'trip_index_picture',
                    ]);
                    if (\count($picturesForTrip) >= self::MAX_PICTURE) {
                        break 2;
                    }
                }
            }
            $trip->setPictures($picturesForTrip);
        }

        return [
            'public' => true,
            'urls' => (string) json_encode([]),
            'user' => $user,
            'trips' => $trips,
        ];
    }

    /** @return array<mixed> */
    #[Route('/{user}/{trip}', name: 'show', methods: ['GET'])]
    #[Template('public/show.html.twig')]
    public function show(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user,
        #[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip,
    ): array {
        if (!$trip->isShared() || $trip->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        $diaryEntries = $this->diaryEntryRepository->findByTrip($trip);
        $routings = $this->tripService->calculateRoutings($trip);

        // Bags
        $bags = $this->bagRepository->findBagsForTripAndUser($trip, $user);

        return [
            'public' => true,
            'urls' => (string) json_encode([]),
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip, true),
            'user' => $user,
            'trip' => $trip,
            'diaryEntries' => $diaryEntries,
            'routings' => $routings,
            'bags' => $bags,
        ];
    }

    #[Route('/{user}/{trip}/js/index.js', name: 'show_js', methods: ['GET'])]
    public function js(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user,
        #[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false,
    ): Response {
        $response = $this->render('public/show.js.twig', array_merge($this->show($user, $trip), ['first_load' => $firstLoad]));
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }

    #[Route('/{user}/{trip}/js/progress.json', name: 'progress_json', methods: ['GET'])]
    public function progress(
        #[MapEntity(mapping: ['user' => 'nickname'])] User $user,
        #[MapEntity(mapping: ['trip' => 'shareKey'])] Trip $trip,
    ): JsonResponse {
        $point = $trip->getProgressPoint();

        return $this->json($point, context: ['groups' => ['public']]);
    }

    #[Route('/{user}/{trip}/photo/{path}', name: 'photo', methods: ['GET'])]
    #[Route('/{user}/{trip}/photo/{filter}/{path}', name: 'photo_with_filter', methods: ['GET'])]
    public function photo(User $user, Trip $trip, Photo $photo, string $filter = 'interest_image'): Response
    {
        CommonHelper::allowMoreResources();

        // Either the user should be the one owning the trip or the trip public
        if (!$trip->isShared() && $this->getUser() !== $trip->getUser()) {
            throw $this->createNotFoundException();
        }

        $url = '/uploads/' . $user->getId() . '/' . $photo->getPath();
        $resolved = $this->filterService->getUrlOfFilteredImage($url, $filter);

        return $this->redirect($resolved);
    }
}
