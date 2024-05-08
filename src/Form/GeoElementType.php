<?php

namespace App\Form;

use App\Service\SearchElementEntries;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class GeoElementType extends AbstractType
{
    public function __construct(
        private readonly SearchElementEntries $searchElementEntries,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->searchElementEntries->getEntries() as $k => $v) {
            $choices[$k] = $v[0];
        }

        $builder
            ->add('element', ChoiceType::class, [
                'placeholder' => 'form.placeholder.choose_an_element',
                'choices' => $choices,
            ])
            // South-West-North-East
            ->add('southWest', GeoPointType::class)
            ->add('northEast', GeoPointType::class)
        ;
    }
}
