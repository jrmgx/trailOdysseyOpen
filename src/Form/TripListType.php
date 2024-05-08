<?php

namespace App\Form;

use App\Entity\Trip;
use App\Entity\User;
use App\Repository\TripRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class TripListType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly TripRepository $tripRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $trips = $this->tripRepository->findByUser($user);
        $builder
            ->add('trips', ChoiceType::class, [
                'choices' => $trips,
                'choice_label' => fn (Trip $trip) => $trip->getName(),
            ])
        ;
    }
}
