<?php

namespace App\Form\Projects;

use App\Entity\Projects\Project;
use App\Entity\UserHandling\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'required' => false,
                'label' => 'Start Date',
            ])
            ->add('end_date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Due Date',
            ])
            ->add('assignedUsers', EntityType::class, [
                'mapped' => false,
                'required' => false,
                'class' => Utilisateur::class,
                'choice_label' => static function (Utilisateur $u): string {
                    $fullName = trim(($u->getPrenom() ?? '') . ' ' . ($u->getNom() ?? ''));
                    $role = trim((string) ($u->getRole() ?? ''));
                    $label = $fullName !== '' ? $fullName : ('User #' . $u->getId());

                    return $role !== '' ? ($role . ' · ' . $label) : $label;
                },
                'multiple' => true,
                'expanded' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
