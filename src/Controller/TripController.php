<?php

namespace App\Controller;

use App\Entity\DiaryEntry;
use App\Entity\GeoPoint;
use App\Entity\Interest;
use App\Entity\Routing;
use App\Entity\Segment;
use App\Entity\Stage;
use App\Entity\Tiles;
use App\Entity\Trip;
use App\Entity\User;
use App\Form\TilesListType;
use App\Form\TripEditType;
use App\Form\TripMapOptionType;
use App\Form\TripType;
use App\Repository\TilesRepository;
use App\Repository\TripRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[IsGranted('ROLE_USER')]
#[Route('/account/trip', name: 'trip_')]
class TripController extends BaseController
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TilesRepository $tilesRepository,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    /** @return array<mixed> */
    #[Route('', name: 'index', methods: ['GET'])]
    #[Template('trip/index.html.twig')]
    public function index(): array
    {
        /** @var User $user */
        $user = $this->getUser();

        return [
            'trips' => $this->tripRepository->findByUser($user),
        ];
    }

    /** @return Response|array<mixed> */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Template('trip/new.html.twig')]
    public function new(Request $request): Response|array
    {
        /** @var User $user */
        $user = $this->getUser();

        $trip = new Trip();
        $this->addDefaultTiles($trip);

        $form = $this->createForm(TripType::class, $trip, [
            'action' => $this->generateUrl('trip_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO update default map center regarding locale
            $geoPoint = new GeoPoint();
            $geoPoint->setLat('47.07386'); // France
            $geoPoint->setLon('3.80676');

            $trip->setMapCenter($geoPoint);
            $trip->setMapZoom(6);
            $trip->setUser($user);

            $this->entityManager->persist($trip);
            $this->entityManager->flush();

            return $this->redirectToRoute('gpx_new', [
                'trip' => $trip->getId(),
                'onBoarding' => true,
            ], Response::HTTP_SEE_OTHER);
        }

        return compact('form');
    }

    /** @return Response|array<mixed> */
    #[Route('/{trip}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('trip/edit.html.twig')]
    public function edit(Request $request, Trip $trip): Response|array
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        /** @var User $user */
        $user = $this->getUser();

        $tilesList = $this->tilesRepository->findTilesForUser(
            $user,
            $trip->getTiles()->map(fn (Tiles $t) => $t->getUrl())->toArray()
        );
        $formTilesList = null;
        if (\count($tilesList) > 0) {
            $formTilesList = $this->createForm(TilesListType::class, options: [
                'tiles' => $tilesList,
            ]);
            $formTilesList->handleRequest($request);
            if ($formTilesList->isSubmitted() && $formTilesList->isValid()) {
                /** @var Tiles $tilesChosen */
                $tilesChosen = $formTilesList->get('tiles')->getData();

                $tiles = new Tiles();
                $tiles->setName($tilesChosen->getName());
                $tiles->setUrl($tilesChosen->getUrl());
                $tiles->setDescription($tilesChosen->getDescription());
                $this->entityManager->persist($tiles);

                $trip->addTile($tiles);
                $trip->updatedNow();

                $this->entityManager->persist($trip);
                $this->entityManager->flush();

                return $this->redirectToRoute('trip_edit', [
                    'trip' => $trip->getId(),
                ], Response::HTTP_SEE_OTHER);
            }
        }

        $form = $this->createForm(TripEditType::class, $trip, [
            'action' => $this->generateUrl('trip_edit', ['trip' => $trip->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();
            $this->entityManager->persist($trip);
            $this->entityManager->flush();

            return $this->redirectToRoute('trip_index', status: Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'form', 'formTilesList');
    }

    /** @return Response|array<mixed> */
    #[Route('/{trip}/edit/map/option', name: 'edit_map_option', options: ['expose' => true], methods: ['POST'])]
    public function mapOption(Request $request, Trip $trip): Response|array
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $form = $this->createForm(TripMapOptionType::class, $trip, [
            'action' => $this->generateUrl('trip_edit_map_option', ['trip' => $trip->getId()]),
            'csrf_protection' => false,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $this->entityManager->persist($trip);
        $this->entityManager->flush();

        return new Response('ok', Response::HTTP_ACCEPTED);
    }

    #[Route('/{trip}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trip $trip): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        if ($this->isCsrfTokenValid('delete' . $trip->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($trip);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{trip}/makePublic', name: 'make_public', methods: ['POST'])]
    public function makePublic(Request $request, Trip $trip): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        if ($this->isCsrfTokenValid('make_public' . $trip->getId(), (string) $request->request->get('_token'))) {
            $trip->startShare();
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{trip}/makePrivate', name: 'make_private', methods: ['POST'])]
    public function makePrivate(Request $request, Trip $trip): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        if ($this->isCsrfTokenValid('make_private' . $trip->getId(), (string) $request->request->get('_token'))) {
            $trip->stopShare();
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{trip}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Trip $trip): Response
    {
        if (!$this->isCsrfTokenValid('duplicate' . $trip->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('trip_index');
        }

        /** @var User $user */
        $user = $this->getUser();

        $tripCopy = new Trip();
        $tripCopy->setDescription($trip->getDescription());
        $tripCopy->setName($trip->getName() . ' - duplicate');
        $tripCopy->setUser($user);
        $tripCopy->setMapCenter($trip->getMapCenter());
        $tripCopy->setMapZoom($trip->getMapZoom());
        $tripCopy->updatedNow();

        $this->entityManager->persist($tripCopy);

        foreach ($trip->getTiles() as $tile) {
            $tileCopy = new Tiles();
            $tileCopy->setName($tile->getName());
            $tileCopy->setUrl($tile->getUrl());
            $tileCopy->setPosition($tile->getPosition());
            $tileCopy->setDescription($tile->getDescription());
            $tileCopy->setOverlay($tile->getOverlay());
            $tileCopy->setPublic($tile->getPublic());
            $tileCopy->setUseProxy($tile->getUseProxy());
            $tileCopy->setGeoJson($tile->getGeoJson());
            $tileCopy->setGeoJsonHtml($tile->getGeoJsonHtml());

            $tripCopy->addTile($tileCopy);
        }

        foreach ($trip->getSegments() as $segment) {
            $segmentCopy = new Segment();
            $segmentCopy->setUser($user);
            $segmentCopy->setName($segment->getName());
            $segmentCopy->setTrip($tripCopy);
            $segmentCopy->setBoundingBox($segment->getBoundingBox());
            $segmentCopy->setPoints($segment->getPoints());

            $this->entityManager->persist($segmentCopy);
            $tripCopy->addSegment($segmentCopy);
        }

        foreach ($trip->getInterests() as $interest) {
            $interestCopy = new Interest();
            $interestCopy->setTrip($tripCopy);
            $interestCopy->setName($interest->getName());
            $interestCopy->setPointName($interest->getPointName());
            $interestCopy->setUser($user);
            $interestCopy->setDescription($interest->getDescription());
            $interestCopy->setArrivingAt($interest->getArrivingAt());
            $interestCopy->setPoint($interest->getPoint());
            $interestCopy->setType($interest->getType());
            $interestCopy->setSymbol($interest->getSymbol());

            $this->entityManager->persist($interestCopy);
            $tripCopy->addInterest($interestCopy);
        }

        foreach ($trip->getDiaryEntries() as $diaryEntry) {
            $diaryCopy = new DiaryEntry();
            $diaryCopy->setTrip($tripCopy);
            $diaryCopy->setName($diaryEntry->getName());
            $diaryCopy->setPointName($diaryEntry->getPointName());
            $diaryCopy->setUser($user);
            $diaryCopy->setDescription($diaryEntry->getDescription());
            $diaryCopy->setArrivingAt($diaryEntry->getArrivingAt());
            $diaryCopy->setPoint($diaryEntry->getPoint());
            $diaryCopy->setType($diaryEntry->getType());
            $diaryCopy->setSymbol($diaryEntry->getSymbol());

            $this->entityManager->persist($diaryCopy);
            $tripCopy->addDiaryEntry($diaryCopy);
        }

        /** @var array<int, Routing> $routingCopies */
        $routingCopies = [];
        foreach ($trip->getStages() as $stage) {
            $stageCopy = new Stage();
            $stageCopy->setTrip($tripCopy);
            $stageCopy->setName($stage->getName());
            $stageCopy->setPointName($stage->getPointName());
            $stageCopy->setUser($user);
            $stageCopy->setDescription($stage->getDescription());
            $stageCopy->setArrivingAt($stage->getArrivingAt());
            $stageCopy->setLeavingAt($stage->getLeavingAt());
            $stageCopy->setPoint($stage->getPoint());
            $stageCopy->setSymbol($stage->getSymbol());
            $stageCopy->setTimezone($stage->getTimezone());

            $this->entityManager->persist($stageCopy);
            $tripCopy->addStage($stageCopy);

            $routingIn = $stage->getRoutingIn();
            if ($routingIn) {
                if (!isset($routingCopies[$routingIn->getId()])) {
                    $routingCopy = new Routing();
                    $routingCopy->setUser($user);
                    $routingCopy->setTrip($tripCopy);
                    $routingCopy->setAsTheCrowFly($routingIn->getAsTheCrowFly());
                    $routingCopy->setDistance($routingIn->getDistance());
                    $routingCopy->setPathPoints($routingIn->getPathPoints());
                    $routingCopy->setFinishStage($stageCopy);

                    $routingCopies[$routingIn->getId()] = $routingCopy;

                    $this->entityManager->persist($routingCopy);
                    $tripCopy->addRouting($routingCopy);
                } else {
                    $routingCopy = $routingCopies[$routingIn->getId()];
                    $routingCopy->setFinishStage($stageCopy);
                }
            }

            $routingOut = $stage->getRoutingOut();
            if ($routingOut) {
                if (!isset($routingCopies[$routingOut->getId()])) {
                    $routingCopy = new Routing();
                    $routingCopy->setUser($user);
                    $routingCopy->setTrip($tripCopy);
                    $routingCopy->setAsTheCrowFly($routingOut->getAsTheCrowFly());
                    $routingCopy->setDistance($routingOut->getDistance());
                    $routingCopy->setPathPoints($routingOut->getPathPoints());
                    $routingCopy->setStartStage($stageCopy);

                    $routingCopies[$routingOut->getId()] = $routingCopy;

                    $this->entityManager->persist($routingCopy);
                    $tripCopy->addRouting($routingCopy);
                } else {
                    $routingCopy = $routingCopies[$routingOut->getId()];
                    $routingCopy->setStartStage($stageCopy);
                }
            }
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('trip_index');
    }

    private function addDefaultTiles(Trip $trip): void
    {
        $defaultTiles = new Tiles();
        $defaultTiles->setUrl(Tiles::OSM_DEFAULT);
        $defaultTiles->setName('Open Street Map');

        $trip->addTile($defaultTiles);
        $defaultTiles->setTrip($trip);
    }
}
