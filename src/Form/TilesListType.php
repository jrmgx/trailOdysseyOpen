<?php

namespace App\Form;

use App\Entity\Tiles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TilesListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, Tiles> $tiles */
        $tiles = $options['tiles'];
        $builder
            ->add('tiles', ChoiceType::class, [
                'label' => 'form.label.add_existing_tiles',
                'choices' => $tiles,
                'choice_label' => fn (Tiles $tiles) => $tiles->getName() . ' (' . $tiles->getUrl() . ')',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('tiles', []);
    }
}
