<?php

namespace App\Form;

use App\Entity\GeoPoint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeoPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lon', options: ['label' => 'form.label.lon'])
            ->add('lat', options: ['label' => 'form.label.lat'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GeoPoint::class,
        ]);
    }
}
