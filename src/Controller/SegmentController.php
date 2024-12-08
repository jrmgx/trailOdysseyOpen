<?php

namespace App\Controller;

use App\Entity\Segment;
use App\Entity\Trip;
use App\Form\SegmentMultipleDeleteType;
use App\Form\SegmentType;
use App\Form\TripMapOptionType;
use App\Model\Point;
use App\Repository\SegmentRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The name was 'segment' but it gets blocked by adBlock (with the .js extension seen later).
 */
#[IsGranted('ROLE_USER')]
#[Route('/account/trip/{trip}/paths', name: 'segment_')]
class SegmentController extends BaseController
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    /** @return array<mixed> */
    #[Route('', name: 'show', options: ['expose' => true], methods: ['GET'])]
    #[Template('segment/index.html.twig')]
    public function show(Trip $trip): array
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $saveMapOptionForm = $this->createForm(TripMapOptionType::class, $trip, [
            'action' => $this->generateUrl('trip_edit_map_option', ['trip' => $trip->getId()]),
            'csrf_protection' => false,
        ]);

        $segmentMultipleDeleteForm = $this->createForm(SegmentMultipleDeleteType::class, options: ['trip' => $trip]);

        return [
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip),
            'save_map_option_form' => $saveMapOptionForm,
            'segment_multiple_delete_form' => $segmentMultipleDeleteForm,
            'trip' => $trip,
            'segments' => $trip->getSegments(),
        ];
    }

    /**
     * Here the name of the script which was 'segments' is blocked by adBlock
     * So we go for another name. #LOL.
     */
    #[Route('/js/paths.js', name: 'show_js', methods: ['GET'])]
    public function js(
        Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false,
    ): Response {
        $response = $this->render('segment/index.js.twig', array_merge($this->show($trip), ['first_load' => $firstLoad]));
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }

    /** @return Response|array<mixed> */
    #[Route('/new/{lat}/{lon}/entry', name: 'new', options: ['expose' => true], methods: ['GET', 'POST'])]
    #[Template('segment/new_frame.html.twig')]
    public function new(Request $request, Trip $trip, string $lat, string $lon): array|Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $pointA = new Point($lat, (string) ((float) $lon + 0.001));
        $pointB = new Point($lat, (string) ((float) $lon - 0.001));

        $segment = new Segment();
        $segment->setPoints([$pointA, $pointB]);

        return $this->handleForm($request, $trip, $segment, $this->generateUrl('segment_new', [
            'trip' => $trip->getId(),
            'lat' => $lat,
            'lon' => $lon,
        ]));
    }

    /** @return Response|array<mixed> */
    #[Route('/new/itinerary', name: 'new_itinerary', options: ['expose' => true], methods: ['GET', 'POST'])]
    #[Template('segment/new_itinerary_frame.html.twig')]
    public function itinerary(Request $request, Trip $trip): array|Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $pointZero = new Point('0', '0');

        $segment = new Segment();
        $segment->setPoints([$pointZero, $pointZero]);

        return $this->handleForm($request, $trip, $segment, $this->generateUrl('segment_new_itinerary', [
            'trip' => $trip->getId(),
        ]));
    }

    #[Route('/new/{lat}/{lon}/{id}', name: 'split', options: ['expose' => true], methods: ['GET', 'POST'])] // TODO Change to post
    public function split(Trip $trip, string $lat, string $lon, Segment $segment): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $segment);

        $splitPoint = new Point($lat, $lon);

        $current = true;
        $pointsCurrent = [];
        $pointsNext = [];
        foreach ($segment->getPoints() as $point) {
            if ($current) {
                $pointsCurrent[] = $point;
            } else {
                $pointsNext[] = $point;
            }
            if ($point->equalsWithoutElevation($splitPoint)) {
                $current = false;
                $pointsNext[] = $point;
            }
        }
        $name = $segment->getName();
        $segment->setName($name . ' - A');
        $segment->setPoints($pointsCurrent);

        $segmentB = new Segment();
        $segmentB->setName($name . ' - B');
        $segmentB->setPoints($pointsNext);
        $segmentB->setTrip($trip);
        $segmentB->setUser($trip->getUser());

        $trip->updatedNow();
        $this->entityManager->persist($segmentB);
        $this->entityManager->flush();

        return $this->redirectToRoute('segment_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('segment/edit_frame.html.twig')]
    public function edit(Request $request, Trip $trip, Segment $segment): array|Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $segment);

        return $this->handleForm($request, $trip, $segment, $this->generateUrl('segment_edit', [
            'trip' => $trip->getId(),
            'id' => $segment->getId(),
        ]));
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trip $trip, Segment $segment): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $segment);

        if (!$this->isCsrfTokenValid('delete' . $segment->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException();
        }

        $trip->updatedNow();
        $this->entityManager->remove($segment);
        $this->entityManager->flush();

        return $this->redirectToRoute('segment_show', ['trip' => $trip->getId()]);
    }

    #[Route('/delete/multiple', name: 'delete_multiple', methods: ['POST'])]
    public function deleteMultiple(Request $request, Trip $trip): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $form = $this->createForm(SegmentMultipleDeleteType::class, options: ['trip' => $trip]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $ids = array_map(
            fn (string $id) => (int) $id,
            explode(',', $form->get('ids')->getData())
        );
        $segments = $this->segmentRepository->findByIds($ids);

        foreach ($segments as $segment) {
            $this->denyAccessUnlessGranted(UserVoter::EDIT, $segment);
            $this->entityManager->remove($segment);
        }

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('segment_show', ['trip' => $trip->getId()]);
    }

    /**
     * @return array<mixed>|Response
     */
    private function handleForm(Request $request, Trip $trip, Segment $segment, string $formActionUrl): array|Response
    {
        $form = $this->createForm(SegmentType::class, $segment, ['action' => $formActionUrl]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // TODO this could be handled with a formView/Model
                $jsonPoints = json_decode($form->get('jsonPoints')->getData(), true);
                $points = array_map(
                    fn (array $jsonPoint) => new Point($jsonPoint['lat'], $jsonPoint['lon'], $jsonPoint['el'] ?? null),
                    $jsonPoints
                );
                $segment->setPoints($points);
                if (\count($points) < 2) {
                    throw new \Exception('Segment needs at least two points.');
                }

                $segment->setUser($trip->getUser());
                $segment->setTrip($trip);

                $trip->updatedNow();
                $this->entityManager->persist($segment);
                $this->entityManager->flush();

                return $this->redirectToRoute('segment_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return compact('trip', 'segment', 'form');
    }
}
