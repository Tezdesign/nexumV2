<?php

namespace App\Form\Tasks;

use App\Entity\Tasks\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('status')
            ->add('priority')
            ->add('start_date')
            ->add('due_date')
            ->add('estimated_time')
            ->add('actual_time')
            ->add('created_at')
            ->add('updated_at')
            ->add('project_id')
            ->add('assigned_to')
            ->add('created_by')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}
