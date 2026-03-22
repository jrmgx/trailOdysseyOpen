<?php

namespace App\Form;

use App\Entity\Interest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InterestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('symbol', TextType::class, [
                'label' => 'form.label.symbol',
            ])
            ->add('name', TextType::class, [
                'label' => 'form.label.name',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.label.description',
                'required' => false,
            ])
            ->add('point', GeoPointType::class, [
                'label' => 'form.label.point',
            ])
            ->add('arrivingAt', DateType::class, [
                'label' => 'form.label.arriving_at',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'view_timezone' => 'UTC',
            ])
            ->add('checkpoint', CheckboxType::class, [
                'label' => 'form.label.checkpoint',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Interest::class,
        ]);
    }
}
