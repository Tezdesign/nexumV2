<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

final class PdfQuizUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pdf', FileType::class, [
            'label' => 'Fichier PDF',
            'mapped' => false,
            'required' => true,
            'attr' => [
                'accept' => 'application/pdf,.pdf',
            ],
            'constraints' => [
                new NotNull([
                    'message' => 'Veuillez sélectionner un fichier PDF.',
                ]),
                new File([
                    'maxSize' => '25M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/x-pdf',
                    ],
                    'mimeTypesMessage' => 'Veuillez uploader un fichier PDF valide.',
                ]),
            ],
        ]);
    }
}

