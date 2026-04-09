<?php

namespace App\Form\Projects;

use App\Entity\Projects\Project;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Support\UserDisplayName;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
    /**
     * @param array{assignable_users?: int[]} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var int[] $assignableUsers */
        $assignableUsers = array_values(array_unique(array_map('intval', $options['assignable_users'] ?? [])));

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
            ->add('assignedUsers', EntityType::class, [
                'mapped' => false,
                'required' => true,
                'class' => Utilisateur::class,
                'choice_label' => static function (Utilisateur $u): string {
                    $fullName = UserDisplayName::format($u, $u->getId());
                    $role = trim((string) ($u->getRole() ?? ''));
                    return $role !== '' ? ($role . ' · ' . $fullName) : $fullName;
                },
                'query_builder' => static function (UtilisateurRepository $repo) use ($assignableUsers) {
                    $qb = $repo->createQueryBuilder('u')
                        ->andWhere('LOWER(u.role) NOT LIKE :adminRole')
                        ->setParameter('adminRole', '%admin%')
                        ->orderBy('u.role', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC')
                        ->addOrderBy('u.nom', 'ASC');

                    if ($assignableUsers !== []) {
                        $qb
                            ->andWhere('u.id IN (:ids)')
                            ->setParameter('ids', $assignableUsers);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
                'multiple' => true,
                'expanded' => false,
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Select at least one team member.'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'assignable_users' => [],
        ]);
        $resolver->setAllowedTypes('assignable_users', 'array');
    }
}
