<?php

namespace App\Controller;

use App\Entity\DiaryEntry;
use App\Entity\GeoPoint;
use App\Entity\MappableInterface;
use App\Entity\Stage;
use App\Entity\Trip;
use App\Entity\User;
use App\Override\LeagueCommonMarkConverterFactory;
use App\Repository\DiaryEntryRepository;
use App\Repository\InterestRepository;
use App\Repository\StageRepository;
use App\Security\Voter\UserVoter;
use App\Service\GeoCodingService;
use App\Service\MastodonService;
use App\Service\RoutingService;
use App\Service\TripService;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vazaha\Mastodon\Models\MediaAttachmentModel;

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
        protected readonly MastodonService $mastodonService,
        protected readonly LeagueCommonMarkConverterFactory $leagueCommonMarkConverterFactory,
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly string $uploadsDirectory,
        SerializerInterface $serializer,
    ) {
        parent::__construct($serializer);
    }

    protected function commonNew(
        Request $request,
        Trip $trip,
        string $lat,
        string $lon,
        MappableInterface $mappable,
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
        string $routeRedirect,
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

            if ($mappable instanceof DiaryEntry) {
                $this->commonBroadcast($mappable, $form);
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

    protected function commonBroadcast(DiaryEntry $diaryEntry, FormInterface $form): void
    {
        // TODO move this to messenger
        /** @var User $user */
        $user = $this->getUser();
        if ($form->has('broadcast') && $form->get('broadcast')->getData()) {
            // For now we only have Mastodon as a possible broadcast
            if ($user->isConnectedToMastodon()) {
                /** @var array<int, MediaAttachmentModel> $media */
                $media = [];
                $images = $this->findImages($diaryEntry->getDescription() ?? '');
                $text = $this->convert($diaryEntry->getDescription() ?? '');
                $text .= "\n\n";
                $text .= $this->urlGenerator->generate('public_show', [
                    'trip' => $diaryEntry->getTrip()->getShareKey(),
                    'user' => $diaryEntry->getUser()->getNickname(),
                ], UrlGeneratorInterface::ABSOLUTE_URL) . '#' . $diaryEntry->getId();
                foreach ($images as $image) {
                    $url = $image->getUrl();
                    $parts = explode('/', $url);
                    $fileName = end($parts);
                    $filePath = $this->uploadsDirectory . '/' . $user->getId() . '/' . $fileName;
                    $media[] = $this->mastodonService->uploadMedia($user, $filePath, $this->getAltText($image));
                }

                $mastodonUri = $diaryEntry->getBroadcastIdentifiers()['mastodon'] ?? null;
                if ($mastodonUri) {
                    $this->mastodonService->editStatus($user, $mastodonUri, $text, $media);
                } else {
                    $status = $this->mastodonService->postStatus($user, $text, $media);
                    $diaryEntry->addBroadcastIdentifier('mastodon', $status->uri);
                    $this->entityManager->flush();
                }
            }
        }
    }

    private function convert(string $description): string
    {
        // Mastodon supports those HTML tags as a render but not as creation
        // p, del, pre, blockquote, code, b, strong, u, i, em, ul, ol, li
        // So we are removing everything
        $converter = ($this->leagueCommonMarkConverterFactory)();

        return strip_tags(
            $converter->convert($description)->getContent(),
            // '<p><del><pre><blockquote><code><b><strong><u><i><em><ul><ol><li>'
        );
    }

    /**
     * @return array<int, Image>
     */
    private function findImages(string $description): array
    {
        $converter = ($this->leagueCommonMarkConverterFactory)();
        $rendered = $converter->convert($description);
        $list = [];

        return $this->findImageNodes($rendered->getDocument(), $list);
    }

    private function getAltText(Image $node): string
    {
        $altText = '';

        foreach ((new NodeIterator($node)) as $n) {
            if ($n instanceof StringContainerInterface) {
                $altText .= $n->getLiteral();
            } elseif ($n instanceof Newline) {
                $altText .= "\n";
            }
        }

        return $altText;
    }

    /**
     * @param array<int, Image> $flatList
     *
     * @return array<int, Image>
     */
    private function findImageNodes(Node $node, array &$flatList = []): array
    {
        if ($node instanceof Image) {
            $flatList[] = $node;
        }

        $child = $node->firstChild();
        while (null !== $child) {
            $this->findImageNodes($child, $flatList);
            $child = $child->next();
        }

        return $flatList;
    }
}
