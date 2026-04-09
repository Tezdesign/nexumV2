<?php

namespace App\Form\Projects;

use App\Entity\Projects\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Manager edit rules:
 * - Keep the modal focused on the editable project metadata.
 * - Team membership is handled separately in the add-members modal.
 */
final class ProjectManagerUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
            ])
            ->add('start_date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Start Date',
            ])
            ->add('end_date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Due Date',
            ])
            ->add('budget', TextType::class, [
                'required' => false,
                'label' => 'Budget',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
