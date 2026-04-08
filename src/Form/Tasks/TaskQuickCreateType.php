<?php

namespace App\Form\Tasks;

use App\Entity\Projects\Project;
use App\Entity\Tasks\Task;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\Projects\ProjectRepository;
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
use Doctrine\ORM\QueryBuilder;

final class TaskQuickCreateType extends AbstractType
{
    /**
     * @param array{is_manager?: bool, allowed_project_ids?: int[]} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isManager = (bool) ($options['is_manager'] ?? false);
        $allowedProjectIds = array_values(array_unique(array_map('intval', $options['allowed_project_ids'] ?? [])));

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
                'query_builder' => static function (ProjectRepository $repo) use ($isManager, $allowedProjectIds): QueryBuilder {
                    $qb = $repo->createQueryBuilder('p')
                        ->orderBy('p.updated_at', 'DESC')
                        ->addOrderBy('p.id', 'DESC');

                    if (!$isManager) {
                        if ($allowedProjectIds === []) {
                            $qb->andWhere('1 = 0');
                        } else {
                            $qb
                                ->andWhere('p.id IN (:ids)')
                                ->setParameter('ids', $allowedProjectIds);
                        }
                    }

                    return $qb;
                },
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

        if ($isManager) {
            $builder->add('assignedUser', EntityType::class, [
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
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'validation_groups' => ['Default', 'task_quick_create'],
            'is_manager' => false,
            'allowed_project_ids' => [],
        ]);
        $resolver->setAllowedTypes('is_manager', 'bool');
        $resolver->setAllowedTypes('allowed_project_ids', 'array');
    }
}
