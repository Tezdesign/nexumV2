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
    /**
     * @param array{allow_assigned_user?: bool, member_ids?: int[]} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowAssignedUser = (bool) ($options['allow_assigned_user'] ?? false);
        $memberIds = array_values(array_unique(array_map('intval', $options['member_ids'] ?? [])));

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

        if ($allowAssignedUser) {
            $builder->add('assignedUser', EntityType::class, [
                'mapped' => false,
                'required' => false,
                'class' => Utilisateur::class,
                'placeholder' => 'Select team ...',
                'label' => 'Assigned To',
                'choice_label' => static fn (Utilisateur $u): string => trim(strip_tags((string) ($u->getPrenom().' '.$u->getNom()))),
                'query_builder' => static function (UtilisateurRepository $repo) use ($memberIds) {
                    $qb = $repo->createQueryBuilder('u')
                        ->andWhere('LOWER(u.role) NOT LIKE :adminRole')
                        ->setParameter('adminRole', '%admin%')
                        ->orderBy('u.prenom', 'ASC')
                        ->addOrderBy('u.nom', 'ASC');

                    if ($memberIds !== []) {
                        $qb
                            ->andWhere('u.id IN (:ids)')
                            ->setParameter('ids', $memberIds);
                    }

                    return $qb;
                },
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'allow_assigned_user' => false,
            'member_ids' => [],
        ]);
        $resolver->setAllowedTypes('allow_assigned_user', 'bool');
        $resolver->setAllowedTypes('member_ids', 'array');
    }
}
