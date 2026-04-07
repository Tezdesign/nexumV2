<?php

namespace App\Form\Tasks;

use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TaskQuickCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Task Title',
            ])
            ->add('project', EntityType::class, [
                'mapped' => false,
                'required' => true,
                'class' => Project::class,
                'choice_label' => static fn (Project $p): string => (string) $p->getName(),
                'placeholder' => 'Select project...',
                'label' => 'Project',
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'To Do' => 'todo',
                    'In Progress' => 'in_progress',
                    'Done' => 'done',
                ],
                'placeholder' => 'Select status...',
                'label' => 'Status',
            ])
            ->add('due_date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Due Date',
            ])
            ->add('priority', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'High' => 'high',
                    'Medium' => 'medium',
                    'Low' => 'low',
                ],
                'placeholder' => 'Select priority...',
                'label' => 'Priority',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}

