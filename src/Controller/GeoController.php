<?php

namespace App\Controller;

use App\Entity\GeoPoint;
use App\Entity\Trip;
use App\Form\GeoElementType;
use App\Form\GeoSearchType;
use App\Service\GeoCodingService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/trip/{trip}/geo', name: 'geo_')]
class GeoController extends AbstractController
{
    public function __construct(
        private readonly GeoCodingService $geoCodingService,
    ) {
    }

    /** @return array<mixed> */
    #[Route('/search/{type}', name: 'search', methods: ['GET'])]
    #[Template('geo/search_frame.html.twig')]
    public function search(Trip $trip, string $type): array
    {
        $form = $this->createForm(GeoSearchType::class, options: [
            'action' => $this->generateUrl('geo_suggest', [
                'trip' => $trip->getId(),
                'type' => $type,
            ]),
        ]);

        return compact('type', 'trip', 'form');
    }

    /** @return array<mixed> */
    #[Route('/suggest/{type}', name: 'suggest', methods: ['POST'])]
    #[Template('geo/suggest_frame.html.twig')]
    public function suggest(Request $request, Trip $trip, string $type): array
    {
        $form = $this->createForm(GeoSearchType::class, options: [
            'action' => $this->generateUrl('geo_suggest', [
                'trip' => $trip->getId(),
                'type' => $type,
            ]),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException('Search form is not valid.');
        }

        /** @var string $address */
        $address = $form->get('address')->getData();

        return [
            'type' => $type,
            'trip' => $trip,
            'results' => $this->geoCodingService->suggestAddresses($address),
            'name' => $address,
        ];
    }

    /** @return Response|array<mixed> */
    #[Route('/elements', name: 'elements', methods: ['GET', 'POST'])]
    #[Template('geo/elements_frame.html.twig')]
    public function elements(Request $request, Trip $trip): array|Response
    {
        $form = $this->createForm(GeoElementType::class, options: [
            'action' => $this->generateUrl('geo_elements', ['trip' => $trip->getId()]),
        ]);
        $form->handleRequest($request);
        $results = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var GeoPoint $southWest */
            $southWest = $form->get('southWest')->getData();
            /** @var GeoPoint $northEast */
            $northEast = $form->get('northEast')->getData();
            $keyValue = explode('=', (string) $form->get('element')->getData());
            $results = $this->geoCodingService->searchElements(
                $southWest->toPoint(),
                $northEast->toPoint(),
                $keyValue[0],
                $keyValue[1]
            );
        }

        return compact('trip', 'form', 'results');
    }
}
