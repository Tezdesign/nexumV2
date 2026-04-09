<?php

namespace App\Form;

use App\Entity\Quiz;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
class QuizType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextareaType::class, [
                'label' => 'Question',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Entrez la question',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La question est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'minMessage' => 'La question doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('r1', TextType::class, [
                'label' => 'Réponse 1',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez la réponse 1',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La réponse 1 est obligatoire.']),
                ],
            ])
            ->add('r2', TextType::class, [
                'label' => 'Réponse 2',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez la réponse 2',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La réponse 2 est obligatoire.']),
                ],
            ])
            ->add('r3', TextType::class, [
                'label' => 'Réponse 3',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez la réponse 3',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La réponse 3 est obligatoire.']),
                ],
            ])
            ->add('correct', ChoiceType::class, [
                'label' => 'Bonne réponse',
                'choices' => [
                    'Réponse 1' => 1,
                    'Réponse 2' => 2,
                    'Réponse 3' => 3,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir la bonne réponse.']),
                    new Choice([
                        'choices' => [1, 2, 3],
                        'message' => 'La bonne réponse doit être 1, 2 ou 3.',
                    ]),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image (optionnelle)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (jpg, png, webp).',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quiz::class,
        ]);
    }
}