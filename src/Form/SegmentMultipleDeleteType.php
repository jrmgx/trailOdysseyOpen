<?php

namespace App\Form;

use App\Entity\Trip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SegmentMultipleDeleteType extends AbstractType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Trip $trip */
        $trip = $options['trip'];
        $builder
            ->add('ids', HiddenType::class)
            ->setAction($this->urlGenerator->generate('segment_delete_multiple', ['trip' => $trip->getId()]))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('trip');
    }
}
