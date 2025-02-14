<?php

namespace App\Controller;

use App\Entity\Gear;
use App\Entity\User;
use App\Form\GearType;
use App\Repository\GearRepository;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/account/gear', name: 'gear_')]
class GearController extends AbstractController
{
    public function __construct(
        private readonly GearRepository $gearRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<mixed> */
    #[Route('/', name: 'index', methods: ['GET'])]
    #[Template('gear/index.html.twig')]
    public function index(): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $gears = $this->gearRepository->findByUserOrderedByName($user);

        return compact('gears');
    }

    /** @return Response|array<mixed> */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Template('gear/new.html.twig')]
    public function new(Request $request): Response|array
    {
        $tripId = $request->query->get('trip');
        $gear = new Gear();
        $form = $this->createForm(GearType::class, $gear, [
            'action' => $this->generateUrl('gear_new', ['trip' => $tripId]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $gear->setUser($user);
            $this->entityManager->persist($gear);
            $this->entityManager->flush();

            if ($tripId) {
                return $this->redirectToRoute('bag_index', ['trip' => $tripId], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('gear_index', status: Response::HTTP_SEE_OTHER);
        }

        return compact('form');
    }

    /** @return Response|array<mixed> */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::EDIT, 'gear')]
    #[Template('gear/edit.html.twig')]
    public function edit(Request $request, Gear $gear): Response|array
    {
        $form = $this->createForm(GearType::class, $gear, [
            'action' => $this->generateUrl('gear_edit', ['id' => $gear->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($gear);
            $this->entityManager->flush();

            return $this->redirectToRoute('gear_index', status: Response::HTTP_SEE_OTHER);
        }

        return compact('gear', 'form');
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Gear $gear): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $gear);
        if ($this->isCsrfTokenValid('delete' . $gear->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($gear);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('gear_index', status: Response::HTTP_SEE_OTHER);
    }
}
