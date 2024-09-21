<?php

namespace App\Controller;

use App\Entity\DiaryEntry;
use App\Entity\MappableInterface;
use App\Entity\Photo;
use App\Entity\Trip;
use App\Entity\User;
use App\Form\PhotoFileType;
use App\Helper\GeoHelper;
use App\Model\Point;
use App\Security\Voter\UserVoter;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/account/trip/{trip}/photo', name: 'photo_')]
class PhotoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly string $uploadsDirectory,
    ) {
    }

    /** @return Response|array<mixed> */
    #[Route('/{lat}/{lon}/new', name: 'new', options: ['expose' => true], methods: ['GET', 'POST'])]
    #[Template('photo/new.html.twig')]
    public function new(Request $request, Trip $trip, string $lat, string $lon): Response|array
    {
        $this->denyAccessUnlessGranted(UserVoter::EDIT, $trip);

        $form = $this->createForm(PhotoFileType::class, options: [
            'action' => $this->generateUrl('photo_new', [
                'trip' => $trip->getId(),
                'lat' => $lat,
                'lon' => $lon,
            ]),
        ]);
        $form->handleRequest($request);

        $defaultPoint = new Point($lat, $lon);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            /** @var array<UploadedFile> $uploadedFiles */
            $uploadedFiles = $form->get('files')->getData() ?? [];
            foreach ($uploadedFiles as $uploadedFile) {
                $directory = $this->uploadsDirectory . '/' . $user->getId();
                $filename = sha1(random_bytes(96));
                $extension = $uploadedFile->guessExtension();
                $this->logger->debug("Will upload a new photo: $filename");
                try {
                    $uploadedFile->move($directory, "$filename.$extension");
                } catch (FileException $e) {
                    $this->logger->error("Error while uploading: $filename.$extension", ['exception' => $e]);
                    continue;
                }

                $mime = mime_content_type("$directory/$filename.$extension");
                if ($mime && preg_match('`^image/(heif|heic)(-sequence)?$`', $mime)) {
                    $this->logger->debug('Converting HEIC file to JPG format');
                    ImageService::convertHeicToJpg("$directory/$filename.$extension", "$directory/$filename.jpg");
                    unlink("$directory/$filename.$extension");
                    $extension = 'jpg';
                }

                // Create a photo entity for each photo
                $photo = new Photo();
                $photo->setUser($trip->getUser());
                $photo->setTrip($trip);
                $photo->setPath("$filename.$extension");
                $this->entityManager->persist($photo);

                $diaryEntry = new DiaryEntry();
                $diaryEntry->setTrip($trip);
                $diaryEntry->setUser($trip->getUser());
                $diaryEntry->setName('Photo');
                $diaryEntry->setType(MappableInterface::PHOTO_TYPE);

                $description = '![](/' . $user->getId() . '/' . $trip->getId() . '/' . $photo->getPath() . ')';

                $point = null;
                $date = null;

                try {
                    $exif = exif_read_data("$directory/$filename.$extension");
                    if ($exif) {
                        $point = GeoHelper::getPointFromExif($exif);
                        try {
                            // We consider that the picture uploaded is on the same timezone as the user/trip
                            $timezone = new \DateTimeZone($user->getTimezone());
                            if (isset($exif['DateTimeDigitized'])) {
                                $date = new \DateTimeImmutable($exif['DateTimeDigitized'], $timezone);
                            } elseif (isset($exif['DateTimeOriginal'])) {
                                $date = new \DateTimeImmutable($exif['DateTimeOriginal'], $timezone);
                            }
                        } catch (\Exception $e) {
                            // Date related exception, ignore
                            $this->logger->warning("Error while reading date in exif: $filename.$extension", ['exception' => $e]);
                        }
                    }
                } catch (\Exception $e) {
                    // Exif read data exception, ignore
                    $this->logger->warning("Error while reading exif: $filename.$extension", ['exception' => $e]);
                }

                if ($point) {
                    $diaryEntry->setPoint($point->toGeoPoint());
                } else {
                    $diaryEntry->setPoint($defaultPoint->toGeoPoint());
                    $description .= \PHP_EOL . $this->translator->trans('info.gps_information_not_found_on_this_photo');
                }

                if ($date) {
                    $diaryEntry->setArrivingAt($date);
                } else {
                    $diaryEntry->setArrivingAt(new \DateTimeImmutable());
                    $description .= \PHP_EOL . $this->translator->trans('info.date_information_not_found_on_this_photo');
                }

                $diaryEntry->setDescription($description);

                $trip->updatedNow();
                $this->entityManager->persist($diaryEntry);
                $this->entityManager->flush();
                $this->logger->debug("Done with file: $filename.$extension");
            }

            return $this->redirectToRoute('diaryEntry_show', ['trip' => $trip->getId()], Response::HTTP_SEE_OTHER);
        }

        return compact('trip', 'form');
    }
}
