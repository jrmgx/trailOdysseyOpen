<?php

namespace App\Form;

use App\Entity\Tiles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TilesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', HiddenType::class)
            ->add('name', options: ['label' => 'form.label.name'])
            ->add('description', options: [
                'label' => 'form.label.description',
                'required' => false,
            ])
            ->add('url', options: ['label' => 'form.label.url'])
            ->add('overlay', options: [
                'label' => 'form.label.overlay',
                'required' => false,
            ])
            ->add('public', options: ['label' => 'form.label.public'])
            ->add('geoJson', options: ['label' => 'form.label.geo_json'])
            ->add('geoJsonHtml', TextareaType::class, [
                'label' => 'form.label.geo_json_html',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tiles::class,
        ]);
    }
}
