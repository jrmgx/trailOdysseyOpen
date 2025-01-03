<?php

namespace App\Controller;

use App\Entity\GeoPoint;
use App\Entity\Routing;
use App\Entity\Stage;
use App\Entity\Trip;
use App\Form\StageType;
use App\Form\TripMapOptionType;
use App\Helper\GeoHelper;
use App\Message\UpdateElevationMessage;
use App\Security\Voter\UserVoter;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationInterface;

#[IsGranted('ROLE_USER')]
#[Route('/account/trip/{trip}/stage', name: 'stage_')]
class StageController extends MappableController
{
    /** @return array<mixed> */
    #[Route('', name: 'show', methods: ['GET'])]
    #[Template('stage/index.html.twig')]
    public function show(Request $request, Trip $trip): array
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);

        $interests = $this->interestRepository->findByTrip($trip);
        [$results, $stages, $routings, $extras] = $this->tripService->calculateResults($trip);
        [$sumDistance, $sumPositive, $sumNegative] = $this->tripService->calculateSums($trip);
        $saveMapOptionForm = $this->createForm(TripMapOptionType::class, $trip, [
            'action' => $this->generateUrl('trip_edit_map_option', ['trip' => $trip->getId()]),
            'csrf_protection' => false,
        ]);

        return [
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip),
            'save_map_option_form' => $saveMapOptionForm,
            'trip' => $trip,
            'results' => $results,
            'stages' => $stages,
            'routings' => $routings,
            'extras' => $extras,
            'interests' => $interests,
            'sum_distance' => $sumDistance,
            'sum_positive' => $sumPositive,
            'sum_negative' => $sumNegative,
            'tab' => $request->query->get('tab', 'stages'),
            'segments' => $trip->getSegments(),
        ];
    }

    #[Route('/js/stages.js', name: 'show_js', methods: ['GET'])]
    public function js(
        Request $request,
        Trip $trip,
        // TODO firstLoad is probably always true now
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false,
    ): Response {
        // TODO could find a better place to put that
        // + a specific message bus with only one worker should be setup to prevent mass request on the service
        $this->messageBus->dispatch(new UpdateElevationMessage($trip->getId() ?? 0));

        $response = $this->render('stage/index.js.twig', array_merge($this->show($request, $trip), ['first_load' => $firstLoad]));
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }

    /**
     * This URL has a quirks related to the fact that Turbo does not handle URL with a dot in their last part (file part)
     * see https://github.com/hotwired/turbo/issues/385 and https://github.com/hotwired/turbo/issues/480.
     *
     * @return Response|array<mixed>
     */
    #[Route('/new/{lat}/{lon}/entry', name: 'new', options: ['expose' => true], methods: ['GET', 'POST'])]
    #[Template('stage/new_frame.html.twig')]
    public function new(Request $request, Trip $trip, string $lat, string $lon): array|Response
    {
        /** @var Stage $stage */
        $stage = $this->commonNew($request, $trip, $lat, $lon, new Stage());

        $previousStage = $this->stageRepository->findLastStage($trip);
        if ($previousStage) {
            $stage->setArrivingAt($previousStage->getArrivingAt()->modify('+ 1 day'));
        } else {
            $date = (new \DateTimeImmutable('midnight'))->modify('+ 1 day');
            $stage->setArrivingAt($date);
        }

        $form = $this->createForm(StageType::class, $stage, [
            'action' => $this->generateUrl('stage_new', [
                'trip' => $trip->getId(),
                'lat' => $lat,
                'lon' => $lon,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();
            $stage->setUser($trip->getUser());
            $stage->setTrip($trip);
            $this->entityManager->persist($stage);

            // Create associated Routing
            if ($previousStage) {
                $routing = $this->createNewRouting($trip, $previousStage, $stage);
                $this->entityManager->persist($routing);
            }

            $errors = $this->validator->validate($stage);
            if (0 === $errors->count()) {
                $this->entityManager->flush();

                return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
            }

            /** @var ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return compact('trip', 'stage', 'form');
    }

    #[Route('/new/{id}', name: 'split', methods: ['POST'])]
    public function split(Request $request, Trip $trip, Routing $routing): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $startStage = $routing->getStartStage();
        $finishStage = $routing->getFinishStage();
        $steps = max(2, (int) $request->request->get('step', 2));
        $startTime = (int) $startStage->getArrivingAt()->format('U');
        $endTime = (int) $finishStage->getArrivingAt()->format('U');
        $deltaTime = $endTime - $startTime;
        $totalDistance = $routing->getDistance();
        $intermediateDistance = (int) ($totalDistance / $steps);
        $intermediateTime = (int) ($deltaTime / $steps);

        /** @var array<array{0: GeoPoint, 1: \DateTimeImmutable}> $stepsInfo */
        $stepsInfo = [];
        if ($routing->pathPointsNotEmpty() && $routing->getDistance()) {
            for ($i = 1; $i < $steps; ++$i) {
                $distance = $intermediateDistance * $i;
                $time = $intermediateTime * $i;
                $point = GeoHelper::findPointAtDistance($routing->getPathPoints() ?? [], $distance)?->toGeoPoint();
                $date = $startStage->getArrivingAt()->modify("+ $time seconds")->modify('midnight');
                if (!$point) {
                    continue;
                }
                $stepsInfo[] = [$point, $date];
            }
        } else {
            // If the current routing is not a valid path, we fallback on the best effort
            $previousPoint = $startStage->getPoint();
            $nextPoint = $finishStage->getPoint();
            $time = (int) ($deltaTime / 2);
            $point = GeoHelper::midPoint($previousPoint->toPoint(), $nextPoint->toPoint())->toGeoPoint();
            $date = $startStage->getArrivingAt()->modify("+ $time seconds")->modify('midnight');
            $stepsInfo[] = [$point, $date];
        }

        $currentRouting = $routing;
        foreach ($stepsInfo as $info) {
            /**
             * @var GeoPoint           $point
             * @var \DateTimeImmutable $date
             */
            [$point, $date] = $info;
            // New stage
            $intermediateStage = new Stage();
            $intermediateStage->setPoint($point);
            $intermediateStage->setArrivingAt($date);
            $intermediateStage->setUser($trip->getUser());
            $intermediateStage->setTrip($trip);

            $this->geoCodingService->tryUpdatePointName($intermediateStage);

            $this->entityManager->persist($intermediateStage);

            // Update routing
            $currentRouting->setFinishStage($intermediateStage);
            $currentRouting->setPathPoints(null);

            $this->routingService->updatePathPoints($trip, $currentRouting);
            $this->routingService->updateCalculatedValues($currentRouting);

            $this->entityManager->flush();

            // New routing
            $newRouting = $this->createNewRouting($trip, $intermediateStage, $finishStage);
            $this->entityManager->persist($newRouting);

            $currentRouting = $newRouting;
        }

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('stage/edit_frame.html.twig')]
    public function edit(Request $request, Trip $trip, Stage $stage): array|Response
    {
        return $this->commonEdit($request, $trip, $stage, StageType::class, 'stage', 'stage_show');
    }

    #[Route('/{id}/move/{lat}/{lon}', name: 'move', options: ['expose' => true], methods: ['GET', 'POST'])] // TODO post
    public function move(Trip $trip, Stage $stage, string $lat, string $lon): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $stage);

        $stage->getPoint()->setLon($lon);
        $stage->getPoint()->setLat($lat);

        $routing = $stage->getRoutingIn();
        if ($routing) {
            $routing->setPathPoints(null);
            $this->routingService->updatePathPoints($trip, $routing);
            $this->routingService->updateCalculatedValues($routing);
        }

        $routing = $stage->getRoutingOut();
        if ($routing) {
            $routing->setPathPoints(null);
            $this->routingService->updatePathPoints($trip, $routing);
            $this->routingService->updateCalculatedValues($routing);
        }

        $this->geoCodingService->tryUpdatePointName($stage);

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trip $trip, Stage $stage): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $stage);

        if (!$this->isCsrfTokenValid('delete' . $stage->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException();
        }

        $routingIn = $stage->getRoutingIn();
        $routingOut = $stage->getRoutingOut();

        if ($routingIn) {
            $this->entityManager->remove($routingIn);
        }

        if ($routingOut) {
            $this->entityManager->remove($routingOut);
        }

        $trip->updatedNow();
        $this->entityManager->remove($stage);
        $this->entityManager->flush();

        // Need new routing
        if ($routingIn && $routingOut) {
            $needNewRouting = $this->createNewRouting($trip, $routingIn->getStartStage(), $routingOut->getFinishStage());
            $this->entityManager->persist($needNewRouting);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()]);
    }

    private function createNewRouting(Trip $trip, Stage $startStage, Stage $finishStage): Routing
    {
        $routing = new Routing();
        $routing->setUser($trip->getUser());
        $routing->setTrip($trip);
        $routing->setStartStage($startStage);
        $routing->setFinishStage($finishStage);

        $startStage->setRoutingOut($routing);
        $finishStage->setRoutingIn($routing);

        $this->routingService->updatePathPoints($trip, $routing);
        $this->routingService->updateCalculatedValues($routing);

        return $routing;
    }
}
