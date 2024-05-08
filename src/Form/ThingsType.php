<?php

namespace App\Form;

use App\Entity\Bag;
use App\Entity\Gear;
use App\Entity\Things;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This can be Bag or Gear.
 */
class ThingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Bag $bag */
        $bag = $options['bag'];
        /** @var array<int, Things> $things */
        $things = $options['things'];
        $things = array_filter($things, fn (Things $thing) => $thing !== $bag);
        usort($things, fn (Things $a, Things $b) =>
            // Adding an emoji will push the entry to the end
            ($a->isBag() ? '🛄 ' : '') . $a->getName() <=> ($b->isBag() ? '🛄 ' : '') . $b->getName()
        );

        $builder
            ->add('things', ChoiceType::class, [
                'choices' => $things,
                'multiple' => true,
                'expanded' => false,
                'choice_label' => self::getLabel(...),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('things');
        $resolver->setRequired('bag');
    }

    private static function getLabel(Things $thing): string
    {
        $weight = '';
        $bag = $thing->isBag() ? '🛄 ' : '';
        $inBag = $thing->isInCurrentBag() ? ' ☑️' : '';
        if ($thing instanceof Bag) {
            $weight = ' (' . $thing->getTotalWeight() . 'gr)'; // TODO translate
        } elseif (null !== $thing->getWeight()) {
            $weight = ' (' . $thing->getWeight() . 'gr)'; // TODO translate
        }

        return $bag . $thing->getName() . $weight . $inBag;
    }
}
