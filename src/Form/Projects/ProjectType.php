<?php

namespace App\Form\Projects;

use App\Entity\Projects\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('start_date')
            ->add('end_date')
            ->add('budget')
            ->add('progress')
            ->add('created_at')
            ->add('updated_at')
            ->add('created_by')
            ->add('assigned_to')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
