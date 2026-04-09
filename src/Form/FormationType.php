<?php

namespace App\Form;

use App\Entity\Formation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le titre de la formation',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre est obligatoire.',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'max' => 255,
                    ]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Entrez une description',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description est obligatoire.',
                    ]),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
            ])

            ->add('video1File', FileType::class, [
                'label' => 'Vidéo 1',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'video/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-ms-wmv',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une vidéo valide (mp4, webm, ogg, mov, avi, wmv).',
                    ]),
                ],
            ])

            ->add('video2File', FileType::class, [
                'label' => 'Vidéo 2',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'video/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-ms-wmv',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une vidéo valide (mp4, webm, ogg, mov, avi, wmv).',
                    ]),
                ],
            ])

            ->add('video3File', FileType::class, [
                'label' => 'Vidéo 3',
'mapped' => false,
                
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'video/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/ogg',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-ms-wmv',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une vidéo valide (mp4, webm, ogg, mov, avi, wmv).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}