<?php

namespace App\Form;

use App\Entity\Stage;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StageType extends AbstractType
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.label.name',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.label.description',
                'required' => false,
            ])
            ->add('point', GeoPointType::class, [
                'label' => 'form.label.internal.point',
            ])
            ->add('arrivingAt', DateType::class, [
                'label' => 'form.label.arriving_at',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'view_timezone' => 'UTC',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stage::class,
        ]);
    }
}
