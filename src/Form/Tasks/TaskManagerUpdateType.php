<?php

namespace App\Form\Tasks;

use App\Entity\Tasks\Task;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Support\UserDisplayName;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Manager edit rules:
 * - Status cannot be changed after creation.
 * - Name/description/assignment/due date/priority can be edited.
 * - Status can be optionally enabled (only when the task is assigned to the manager).
 */
final class TaskManagerUpdateType extends AbstractType
{
    /**
     * @param array{member_ids?: int[], allow_status?: bool} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var int[] $memberIds */
        $memberIds = array_values(array_unique(array_map('intval', $options['member_ids'] ?? [])));
        $allowStatus = (bool) ($options['allow_status'] ?? false);

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
                'disabled' => !$allowStatus,
            ])
            ->add('assignedUser', EntityType::class, [
                'mapped' => false,
                'required' => false,
                'class' => Utilisateur::class,
                'placeholder' => 'Select team ...',
                'label' => 'Assigned To',
                'choice_label' => static fn (Utilisateur $u): string => UserDisplayName::format($u, $u->getId()),
                'query_builder' => static function (UtilisateurRepository $repo) use ($memberIds): \Doctrine\ORM\QueryBuilder {
                    $qb = $repo->createQueryBuilder('u')
                        ->andWhere('LOWER(u.role) NOT LIKE :adminRole')
                        ->setParameter('adminRole', '%admin%')
                        ->orderBy('u.prenom', 'ASC')
                        ->addOrderBy('u.nom', 'ASC');

                    if ($memberIds !== []) {
                        $qb
                            ->andWhere('u.id IN (:ids)')
                            ->setParameter('ids', $memberIds);
                    } else {
                        // No members: show empty list.
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
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
            'member_ids' => [],
            'allow_status' => false,
        ]);
        $resolver->setAllowedTypes('member_ids', 'array');
        $resolver->setAllowedTypes('allow_status', 'bool');
    }
}
