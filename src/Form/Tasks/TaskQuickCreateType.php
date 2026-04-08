<?php

namespace App\Form\Tasks;

use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskQuickCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => true,
                'label' => 'Task Name',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('project', EntityType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(message: 'Please select a project.'),
                ],
                'class' => Project::class,
                // Keep DB strings as plain text in option labels.
                'choice_label' => static fn (Project $p): string => trim(strip_tags((string) $p->getName())),
                'placeholder' => 'Select project...',
                'label' => 'Project',
            ])
            ->add('status', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'To Do' => 'todo',
                    'In Progress' => 'in_progress',
                    'Done' => 'done',
                ],
                'placeholder' => 'Select status...',
                'label' => 'Status',
            ])
            ->add('assignedUser', EntityType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(message: 'Please assign a user.'),
                ],
                'class' => Utilisateur::class,
                'placeholder' => 'Select team ...',
                'label' => 'Assign User',
                // Keep DB strings as plain text in option labels.
                'choice_label' => static fn (Utilisateur $u): string => trim(strip_tags((string) ($u->getPrenom().' '.$u->getNom()))),
                'query_builder' => static fn (UtilisateurRepository $repo) => $repo->createQueryBuilder('u')
                    ->orderBy('u.prenom', 'ASC')
                    ->addOrderBy('u.nom', 'ASC'),
            ])
            ->add('due_date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Due Date',
            ])
            ->add('priority', ChoiceType::class, [
                'required' => true,
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
            'validation_groups' => ['Default', 'task_quick_create'],
        ]);
    }
}
