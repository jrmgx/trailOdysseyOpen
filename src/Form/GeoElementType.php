<?php

namespace App\Form;

use App\Entity\GeoPoint;
use App\Service\GeoCodingService;
use App\Service\SearchElementEntries;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeoElementType extends AbstractType
{
    public function __construct(
        private readonly SearchElementEntries $searchElementEntries,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $withGoogleMap = (bool) $options['withGoogleMap'];

        $choices = [];
        foreach ($this->searchElementEntries->getEntries() as $k => $v) {
            $choices[$k] = $v[0];
        }

        $builder
            ->add('element', ChoiceType::class, [
                'placeholder' => 'form.placeholder.choose_an_element',
                'choices' => $choices,
                'required' => !$withGoogleMap,
            ])
            // South-West-North-East
            ->add('southWest', GeoPointType::class)
            ->add('northEast', GeoPointType::class)
        ;

        if ($withGoogleMap) {
            $labelPrefix = 'form.label.geocoding_service_provider.';
            $builder
                ->add('provider', ChoiceType::class, [
                    'choices' => [
                        $labelPrefix . GeoCodingService::PROVIDER_OVERPASS => GeoCodingService::PROVIDER_OVERPASS,
                        $labelPrefix . GeoCodingService::PROVIDER_GOOGLE => GeoCodingService::PROVIDER_GOOGLE,
                    ],
                ])
                ->add('search', TextType::class, [
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'form.placeholder.search',
                    ],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['withGoogleMap' => GeoPoint::class]);
    }
}
