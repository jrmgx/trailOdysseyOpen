<?php

namespace App\Controller;

use App\Entity\Interest;
use App\Entity\Trip;
use App\Form\InterestType;
use App\Security\Voter\UserVoter;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/trip/{trip}/interest', name: 'interest_')]
class InterestController extends MappableController
{
    /**
     * This is used for the "cancel" action.
     * We don't really have an interest page, it's the same as stage, but we need the route to exist.
     */
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(Trip $trip): Response
    {
        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()]);
    }

    /**
     * This URL has a quirks related to the fact that Turbo does not handle URL with a dot in their last part (file part)
     * see https://github.com/hotwired/turbo/issues/385 and https://github.com/hotwired/turbo/issues/480.
     *
     * @return Response|array<mixed>
     */
    #[Route('/new/{lat}/{lon}/entry', name: 'new', methods: ['GET', 'POST'])]
    #[Template('interest/new_frame.html.twig')]
    public function new(Request $request, Trip $trip, string $lat, string $lon): array|Response
    {
        /** @var Interest $interest */
        $interest = $this->commonNew($request, $trip, $lat, $lon, new Interest());
        $interest->setArrivingAt(new \DateTimeImmutable());

        $form = $this->createForm(InterestType::class, $interest, [
            'action' => $this->generateUrl('interest_new', [
                'trip' => $trip->getId(),
                'lat' => $lat,
                'lon' => $lon,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();
            $interest->setUser($trip->getUser());
            $interest->setTrip($trip);
            $this->entityManager->persist($interest);
            $this->entityManager->flush();

            return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'interest', 'form');
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('interest/edit_frame.html.twig')]
    public function edit(Request $request, Trip $trip, Interest $interest): array|Response
    {
        return $this->commonEdit($request, $trip, $interest, InterestType::class, 'interest', 'stage_show');
    }

    #[Route('/{id}/move/{lat}/{lon}', name: 'move', methods: ['GET'])] // TODO post
    public function move(Trip $trip, Interest $interest, string $lat, string $lon): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $interest);

        $interest->getPoint()->setLon($lon);
        $interest->getPoint()->setLat($lat);

        $this->geoCodingService->tryUpdatePointName($interest);

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trip $trip, Interest $interest): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $interest);

        if (!$this->isCsrfTokenValid('delete' . $interest->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException();
        }

        $trip->updatedNow();
        $this->entityManager->remove($interest);
        $this->entityManager->flush();

        return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()]);
    }
}
