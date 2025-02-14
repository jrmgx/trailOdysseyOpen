<?php

namespace App\Controller;

use App\Entity\Bag;
use App\Entity\Gear;
use App\Entity\GearInBag;
use App\Entity\Things;
use App\Entity\Trip;
use App\Entity\User;
use App\Form\BagType;
use App\Form\ThingsType;
use App\Form\TripListType;
use App\Repository\BagRepository;
use App\Repository\GearInBagRepository;
use App\Repository\GearRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/account/trip/{trip}/bag', name: 'bag_')]
class BagController extends AbstractController
{
    public function __construct(
        private readonly BagRepository $bagRepository,
        private readonly GearRepository $gearRepository,
        private readonly GearInBagRepository $gearInBagRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<mixed> */
    #[Route('/', name: 'index', methods: ['GET'])]
    #[Template('bag/index.html.twig')]
    public function index(Trip $trip): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $bags = $this->bagRepository->findBagsForTripAndUser($trip, $user);
        $things = $this->getThings($trip);

        $bagForms = [];
        foreach ($bags as $bag) {
            $bagForms[$bag->getId()] = $this->createForm(ThingsType::class, [], [
                'action' => $this->generateUrl('bag_add_in', [
                    'trip' => $trip->getId(),
                    'id' => $bag->getId(),
                ]),
                'things' => $things,
                'bag' => $bag,
            ])->createView();
        }

        return compact('trip', 'bags', 'bagForms');
    }

    /** @return Response|array<mixed> */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Template('bag/new.html.twig')]
    public function new(Request $request, Trip $trip): Response|array
    {
        $bag = new Bag();
        $form = $this->createForm(BagType::class, $bag, [
            'action' => $this->generateUrl('bag_new', ['trip' => $trip->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $bag->setUser($user);
            $bag->setTrip($trip);

            $trip->updatedNow();
            $this->entityManager->persist($bag);
            $this->entityManager->flush();

            return $this->redirectToRoute('bag_index', [
                'trip' => $trip->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'form');
    }

    /** @return Response|array<mixed> */
    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    #[Template('bag/import.html.twig')]
    public function import(Request $request, Trip $trip): Response|array
    {
        $form = $this->createForm(TripListType::class, options: [
            'action' => $this->generateUrl('bag_import', ['trip' => $trip->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Trip $importFromTrip */
            $importFromTrip = $form->get('trips')->getData();
            if ($trip->getId() !== $importFromTrip->getId()) {
                /** @var User $user */
                $user = $this->getUser();
                $bagsToCopy = $this->bagRepository->findBagsForTripAndUser($importFromTrip, $user);
                foreach ($bagsToCopy as $bagToCopy) {
                    $bag = new Bag();
                    $bag->setName($bagToCopy->getName());
                    $bag->setWeight($bagToCopy->getWeight());
                    $bag->setUser($user);
                    $bag->setTrip($trip);
                    $this->entityManager->persist($bag);

                    foreach ($bagToCopy->getGearsInBag() as $gearToCopy) {
                        $gearInBag = new GearInBag();
                        $gearInBag->setGear($gearToCopy->getGear());
                        $gearInBag->setCount($gearToCopy->getCount());
                        $gearInBag->setBag($bag);
                        $this->entityManager->persist($gearInBag);
                    }
                }

                $trip->updatedNow();
                $this->entityManager->flush();
            }

            return $this->redirectToRoute('bag_index', [
                'trip' => $trip->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return [
            'trip' => $trip,
            'form' => $form,
        ];
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::EDIT, 'bag')]
    #[Template('bag/edit.html.twig')]
    public function edit(Request $request, Trip $trip, Bag $bag): Response|array
    {
        $form = $this->createForm(BagType::class, $bag, [
            'action' => $this->generateUrl('bag_edit', [
                'trip' => $trip->getId(),
                'id' => $bag->getId(),
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->updatedNow();
            $this->entityManager->persist($bag);
            $this->entityManager->flush();

            return $this->redirectToRoute('bag_index', [
                'trip' => $trip->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'bag', 'form');
    }

    #[Route('/add/in/{id}', name: 'add_in', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, 'bag')]
    public function addManyInBag(Request $request, Trip $trip, Bag $bag): Response
    {
        $things = $this->getThings($trip);
        $form = $this->createForm(ThingsType::class, options: [
            'things' => $things,
            'bag' => $bag,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BadRequestHttpException();
        }

        $data = $form->getData();
        /** @var array<Bag|Gear> $things */
        $things = $data['things'];

        foreach ($things as $thing) {
            if ($thing instanceof Bag) {
                $bag->addBagsInBag($thing);
            } else {
                $gearInBag = new GearInBag();
                $gearInBag->setBag($bag);
                $gearInBag->setGear($thing);
                $gearInBagExist = $this->gearInBagRepository->findOneByGearAndBag($thing, $bag);
                if ($gearInBagExist) {
                    $gearInBagExist->setCount($gearInBagExist->getCount() + 1);
                } else {
                    $this->entityManager->persist($gearInBag);
                }
            }
        }

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/gear/check/{id}', name: 'gear_check', methods: ['GET'])]
    #[IsGranted(UserVoter::EDIT, 'gearInBag')]
    public function checkGearInBag(Request $request, Trip $trip, GearInBag $gearInBag): Response
    {
        // TODO csrf
        $gearInBag->setChecked((bool) $request->query->get('checked', false));

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/gear/more/{id}', name: 'gear_more', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, 'gearInBag')]
    public function addGearInBag(Trip $trip, GearInBag $gearInBag): Response
    {
        // TODO csrf
        $gearInBag->setCount($gearInBag->getCount() + 1);

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/gear/out/{id}', name: 'gear_out', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, 'gearInBag')]
    public function removeGearInBag(Trip $trip, GearInBag $gearInBag): Response
    {
        // TODO csrf
        if ($gearInBag->getCount() <= 1) {
            $this->entityManager->remove($gearInBag);
        } else {
            $gearInBag->setCount($gearInBag->getCount() - 1);
        }
        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bag/check/{id}', name: 'bag_check', methods: ['GET'])]
    #[IsGranted(UserVoter::EDIT, 'bag')]
    public function checkBagInBag(Request $request, Trip $trip, Bag $bag): Response
    {
        // TODO csrf
        $bag->setChecked((bool) $request->query->get('checked', false));

        $trip->updatedNow();
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bag/out/{id}', name: 'bag_out', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, 'bag')]
    public function removeBagInBag(Trip $trip, Bag $bag): Response
    {
        // TODO csrf
        $trip->updatedNow();
        $bag->setParentBag(null);
        $bag->setChecked(false);
        $this->entityManager->flush();

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, 'bag')]
    public function delete(Request $request, Trip $trip, Bag $bag): Response
    {
        if ($this->isCsrfTokenValid('delete' . $bag->getId(), (string) $request->request->get('_token'))) {
            $trip->updatedNow();
            $this->entityManager->remove($bag);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('bag_index', [
            'trip' => $trip->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return array<int, Things>
     */
    private function getThings(Trip $trip): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $bags = $this->bagRepository->findByTripAndUser($trip, $user);
        $gears = $this->gearRepository->findGearsForUserAndTripWithBagInfo($user);

        $bagIds = array_map(fn (Bag $bag) => $bag->getId(), $bags);
        foreach ($bags as $bag) {
            $bag->setIsInCurrentBag(null !== $bag->getParentBag());
        }

        foreach ($gears as $gear) {
            $isInCurrentBag = $gear->getGearsInBag()
                ->filter(fn (GearInBag $gib) => \in_array($gib->getBag()->getId(), $bagIds, true))
                ->count() > 0
            ;
            $gear->setIsInCurrentBag($isInCurrentBag);
        }

        return array_merge($gears, $bags);
    }
}
