<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\GpxFileType;
use App\Message\UpdateElevationMessage;
use App\Security\Voter\UserVoter;
use App\Service\GpxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use ZipStream\ZipStream;

#[IsGranted('ROLE_USER')]
#[Route('/trip/{trip}/gpx', name: 'gpx_')]
class GpxController extends AbstractController
{
    public function __construct(
        private readonly GpxService $gpxService,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return Response|array<mixed> */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Template('gpx/new.html.twig')]
    public function new(
        Request $request,
        Trip $trip,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_BOOL)] bool $onBoarding = false
    ): Response|array {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $form = $this->createForm(GpxFileType::class, options: [
            'action' => $this->generateUrl('gpx_new', [
                'trip' => $trip->getId(),
                'onBoarding' => $onBoarding,
            ]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<UploadedFile> $files */
            $files = $form->get('files')->getData() ?? [];
            foreach ($files as $file) {
                $gpxFile = $this->gpxService->gpxFile($file);

                $this->gpxService->gpxFileToSegments($gpxFile, $trip);
                $this->gpxService->gpxFileToInterests($gpxFile, $trip);

                $this->entityManager->flush();
            }

            $this->messageBus->dispatch(new UpdateElevationMessage($trip->getId() ?? 0));

            return $this->redirectToRoute('stage_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
        }

        return compact('onBoarding', 'trip', 'form');
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Trip $trip): void
    {
        $slug = new AsciiSlugger();
        $name = mb_strtolower($slug->slug($trip->getName()));
        $zip = new ZipStream(
            sendHttpHeaders: true,
            outputName: $name . '.zip',
        );

        $files = $this->gpxService->buildGpx($trip);
        foreach ($files as $name => $file) {
            $zip->addFile($name . '.gpx', (string) $file->toXML()->saveXML());
        }

        $zip->finish();
        /* @phpstan-ignore-next-line */
        exit;
    }
}
