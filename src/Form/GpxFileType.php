<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType as BaseFileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

class GpxFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', BaseFileType::class, [
                'label' => 'form.label.gpx_files',
                'mapped' => false,
                'required' => true,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new FileConstraint([
                            'maxSize' => '200M',
                            'mimeTypes' => [
                                'application/gpx+xml',
                                'application/xml',
                                'text/xml',
                                'text/gpx+xml',
                            ],
                            'mimeTypesMessage' => 'form.error.please_upload_a_valid_gpx_file',
                        ]),
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
    }
}
