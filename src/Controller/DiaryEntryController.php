<?php

namespace App\Controller;

use App\Entity\DiaryEntry;
use App\Entity\Routing;
use App\Entity\Trip;
use App\Form\DiaryEntryType;
use App\Form\TripMapOptionType;
use App\Model\Path;
use App\Model\Point;
use App\Security\Voter\UserVoter;
use App\Service\GeoArithmeticService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/account/trip/{trip}/diary', name: 'diaryEntry_')]
class DiaryEntryController extends MappableController
{
    /** @return array<mixed> */
    #[Route('', name: 'show', methods: ['GET'])]
    #[Template('diaryEntry/index.html.twig')]
    public function show(Trip $trip): array
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);

        $diaryEntries = $this->diaryEntryRepository->findByTrip($trip);
        $routings = $this->tripService->calculateRoutings($trip);
        $saveMapOptionForm = $this->createForm(TripMapOptionType::class, $trip, [
            'action' => $this->generateUrl('trip_edit_map_option', ['trip' => $trip->getId()]),
            'csrf_protection' => false,
        ]);

        return [
            'options' => $this->getOptions($trip),
            'tiles' => $this->getTiles($trip),
            'save_map_option_form' => $saveMapOptionForm,
            'trip' => $trip,
            'diaryEntries' => $diaryEntries,
            'routings' => $routings,
        ];
    }

    #[Route('/js/diaryEntry.js', name: 'show_js', methods: ['GET'])]
    public function js(
        Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $firstLoad = false,
    ): Response {
        $response = $this->render('diaryEntry/index.js.twig', array_merge($this->show($trip), ['first_load' => $firstLoad]));
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
    #[Template('diaryEntry/new_frame.html.twig')]
    public function new(Request $request, Trip $trip, string $lat, string $lon): array|Response
    {
        /** @var DiaryEntry $diaryEntry */
        $diaryEntry = $this->commonNew($request, $trip, $lat, $lon, new DiaryEntry());
        $diaryEntry->setArrivingAt(new \DateTimeImmutable());

        $form = $this->createForm(DiaryEntryType::class, $diaryEntry, [
            'action' => $this->generateUrl('diaryEntry_new', [
                'trip' => $trip->getId(),
                'lat' => $lat,
                'lon' => $lon,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();
            $diaryEntry->setUser($trip->getUser());
            $diaryEntry->setTrip($trip);
            $this->entityManager->persist($diaryEntry);
            $this->entityManager->flush();

            return $this->redirectToRoute('diaryEntry_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'diaryEntry', 'form');
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('diaryEntry/edit_frame.html.twig')]
    public function edit(Request $request, Trip $trip, DiaryEntry $diaryEntry): array|Response
    {
        return $this->commonEdit($request, $trip, $diaryEntry, DiaryEntryType::class, 'diaryEntry', 'diaryEntry_show');
    }

    #[Route('/{id}/move/{lat}/{lon}', name: 'move', options: ['expose' => true], methods: ['GET'])] // TODO post
    public function move(Trip $trip, DiaryEntry $diaryEntry, string $lat, string $lon): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $diaryEntry);

        $diaryEntry->getPoint()->setLon($lon);
        $diaryEntry->getPoint()->setLat($lat);

        $this->geoCodingService->tryUpdatePointName($diaryEntry);

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('diaryEntry_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trip $trip, DiaryEntry $diaryEntry): Response
    {
        // TODO move to attribute (those and more in general in all controllers)
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $diaryEntry);

        if (!$this->isCsrfTokenValid('delete' . $diaryEntry->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException();
        }

        $trip->updatedNow();
        $this->entityManager->remove($diaryEntry);
        $this->entityManager->flush();

        return $this->redirectToRoute('diaryEntry_show', ['trip' => $trip->getId()]);
    }

    #[Route('/progress/{lat}/{lon}', name: 'update_progress', options: ['expose' => true], methods: ['GET'])] // TODO POST
    public function progress(Trip $trip, string $lat, string $lon): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);

        $routings = $this->tripService->calculateRoutings($trip);

        /** @var array<int, Path> $paths */
        $paths = array_filter(array_map(fn (Routing $routing) => Path::fromRouting($routing), $routings));

        [$closestPoint] = GeoArithmeticService::findClosestPointOnPaths(
            new Point($lat, $lon, '0'),
            $paths
        );

        $trip->setProgressPoint($closestPoint);
        $trip->updatedNow();

        $this->entityManager->flush();

        return $this->redirectToRoute('diaryEntry_show', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }
}
