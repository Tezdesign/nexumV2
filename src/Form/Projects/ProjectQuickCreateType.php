<?php

namespace App\Form\Projects;

use App\Entity\Projects\Project;
use App\Entity\UserHandling\Utilisateur;
use App\Support\UserDisplayName;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProjectQuickCreateType extends AbstractType
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
                    'rows' => 3,
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
                    $label = $fullName;

                    return $role !== '' ? ($role . ' · ' . $label) : $label;
                },
                'multiple' => true,
                'expanded' => false,
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Select at least one team member.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
