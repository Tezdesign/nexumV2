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

class FormationEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formation = $options['data']; // 🔥 récupérer l'objet

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Length(['min' => 3, 'max' => 255]),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length(['min' => 10]),
                ],
            ])

            // 🎬 VIDEO 1
            ->add('video1File', FileType::class, [
                'label' => $formation && $formation->getVideo1()
                    ? 'Remplacer Vidéo 1 (actuelle : '.$formation->getVideo1().')'
                    : 'Ajouter Vidéo 1',

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
                            'video/mp4','video/webm','video/ogg',
                            'video/quicktime','video/x-msvideo','video/x-ms-wmv',
                        ],
                        'mimeTypesMessage' => 'Vidéo invalide.',
                    ]),
                ],
            ])

            // 🎬 VIDEO 2
            ->add('video2File', FileType::class, [
                'label' => $formation && $formation->getVideo2()
                    ? 'Remplacer Vidéo 2 (actuelle : '.$formation->getVideo2().')'
                    : 'Ajouter Vidéo 2',

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
                            'video/mp4','video/webm','video/ogg',
                            'video/quicktime','video/x-msvideo','video/x-ms-wmv',
                        ],
                    ]),
                ],
            ])

            // 🎬 VIDEO 3
            ->add('video3File', FileType::class, [
                'label' => $formation && $formation->getVideo3()
                    ? 'Remplacer Vidéo 3 (actuelle : '.$formation->getVideo3().')'
                    : 'Ajouter Vidéo 3',

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
                            'video/mp4','video/webm','video/ogg',
                            'video/quicktime','video/x-msvideo','video/x-ms-wmv',
                        ],
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