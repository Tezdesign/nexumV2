<?php

namespace App\Form\Tasks;

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

final class TaskUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Task Name',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('due_date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Due Date',
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
            ->add('assignedUser', EntityType::class, [
                'mapped' => false,
                'required' => false,
                'class' => Utilisateur::class,
                'placeholder' => 'Select team ...',
                'label' => 'Assigned To',
                'choice_label' => static fn (Utilisateur $u): string => trim(strip_tags((string) ($u->getPrenom().' '.$u->getNom()))),
                'query_builder' => static fn (UtilisateurRepository $repo) => $repo->createQueryBuilder('u')
                    ->orderBy('u.prenom', 'ASC')
                    ->addOrderBy('u.nom', 'ASC'),
            ])
            ->add('priority', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'HIGH' => 'high',
                    'MEDIUM' => 'medium',
                    'LOW' => 'low',
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

