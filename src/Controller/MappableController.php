<?php

namespace App\Controller;

use App\Entity\GeoPoint;
use App\Entity\MappableInterface;
use App\Entity\Stage;
use App\Entity\Trip;
use App\Repository\DiaryEntryRepository;
use App\Repository\InterestRepository;
use App\Repository\StageRepository;
use App\Security\Voter\UserVoter;
use App\Service\GeoCodingService;
use App\Service\RoutingService;
use App\Service\TripService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class MappableController extends BaseController
{
    public function __construct(
        protected readonly TripService $tripService,
        protected readonly RoutingService $routingService,
        protected readonly GeoCodingService $geoCodingService,
        protected readonly StageRepository $stageRepository,
        protected readonly InterestRepository $interestRepository,
        protected readonly DiaryEntryRepository $diaryEntryRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ValidatorInterface $validator,
        protected readonly LoggerInterface $logger,
        protected readonly MessageBusInterface $messageBus,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    protected function commonNew(
        Request $request,
        Trip $trip,
        string $lat,
        string $lon,
        MappableInterface $mappable
    ): MappableInterface {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $point = new GeoPoint();
        $point->setLat($lat);
        $point->setLon($lon);
        $mappable->setPoint($point);

        // Point name comes from param
        $pointName = $request->get('pointName');
        if (null !== $pointName) {
            $pointNames = explode(',', $pointName);
            $pointName = ucfirst($pointNames[0]);
            $mappable->setPointName($pointName);
        } else {
            $this->geoCodingService->tryUpdatePointName($mappable);
        }

        $name = $request->get('name');
        if ($name) {
            $mappable->setName($name);
        }

        $mappable->setDescription($request->get('description'));

        return $mappable;
    }

    /**
     * @param class-string $formType
     *
     * @return Response|array<mixed>
     */
    protected function commonEdit(
        Request $request,
        Trip $trip,
        MappableInterface $mappable,
        string $formType,
        string $objectName,
        string $routeRedirect
    ): array|Response {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $mappable);

        $dateOfStageBeforeEdit = null;
        if ($mappable instanceof Stage) {
            $dateOfStageBeforeEdit = $mappable->getArrivingAt();
        }

        $form = $this->createForm($formType, $mappable, [
            'action' => $this->generateUrl($objectName . '_edit', [
                'trip' => $trip->getId(),
                'id' => $mappable->getId(),
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();

            $this->entityManager->persist($mappable);

            if ($mappable instanceof Stage && $dateOfStageBeforeEdit && $dateOfStageBeforeEdit !== $mappable->getArrivingAt()) {
                $arrivingAtDiff = $dateOfStageBeforeEdit->diff($mappable->getArrivingAt());
                $currentStage = $mappable;
                while ($currentStage->getRoutingOut()) {
                    $currentStage = $currentStage->getRoutingOut()->getFinishStage();
                    $currentStage->setArrivingAt($currentStage->getArrivingAt()->add($arrivingAtDiff)->setTime(0, 0));
                }
            }

            $this->entityManager->flush();

            return $this->redirectToRoute($routeRedirect, ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
        }

        return [
            'trip' => $trip,
            $objectName => $mappable,
            'form' => $form,
        ];
    }
}
