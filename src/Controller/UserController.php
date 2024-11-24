<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserMastodonType;
use App\Form\UserType;
use App\Service\MastodonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/account/user', name: 'user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MastodonService $mastodonService,
    ) {
    }

    /** @return Response|array<mixed> */
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Template('user/edit.html.twig')]
    public function edit(Request $request): Response|array
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Preferences have been updated!');

            $this->entityManager->flush();

            return $this->redirectToRoute('user_edit', [], Response::HTTP_SEE_OTHER);
        }

        return compact('form', 'user');
    }

    /** @return Response|array<mixed> */
    #[Route('/connect/mastodon', name: 'connect_mastodon', methods: ['GET', 'POST'])]
    #[Template('user/edit_mastodon.html.twig')]
    public function connectToMastodon(Request $request): Response|array
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserMastodonType::class, [
            'url' => $user->getMastodonInfo()['instanceUrl'] ?? null,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $instanceUrl = $form->get('url')->getData() ?? throw new \Exception('Missing Instance Url.');
            $oAuthUrl = $this->mastodonService->oAuthInit($request, $instanceUrl);

            return $this->redirect($oAuthUrl);
        }

        return compact('form', 'user');
    }

    #[Route('/connect/mastodon/code', name: 'connect_mastodon_code', methods: ['GET'])]
    public function connectToMastodonCode(Request $request): Response
    {
        $token = $this->mastodonService->oAuthHandle($request);

        /** @var User $user */
        $user = $this->getUser();
        $user->setMastodonInfo([
            'accessToken' => $token->getToken(),
            'instanceUrl' => $request->getSession()->get('instanceUrl'),
        ]);

        $this->entityManager->flush();

        return $this->redirectToRoute('user_edit', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/disconnect/mastodon', name: 'disconnect_mastodon', methods: ['POST'])]
    public function disconnectFromMastodon(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setMastodonInfo(null);

        $this->entityManager->flush();

        $this->addFlash('success', 'Connection Removed!');

        return $this->redirectToRoute('user_edit', [], Response::HTTP_SEE_OTHER);
    }
}
