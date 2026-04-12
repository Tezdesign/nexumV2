<?php

namespace App\Form\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\Projects\Project;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter budget name'],
                'label' => 'Budget Name'
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a project...',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('p');
                    if ($options['fiscal_start'] && $options['fiscal_end']) {
                        $qb->andWhere('p.end_date >= :fStart')
                           ->andWhere('p.start_date <= :fEnd')
                           ->setParameter('fStart', $options['fiscal_start']->format('Y-m-d'))
                           ->setParameter('fEnd', $options['fiscal_end']->format('Y-m-d'));
                    }
                    return $qb->orderBy('p.name', 'ASC');
                },
                'attr' => ['class' => 'form-select select2', 'data-toggle' => 'select2'],
                'label' => 'Linked Project'
            ])
            ->add('total_budget', NumberType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. 50000'],
                'label' => 'Total Budget'
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'ON TRACK' => 'ON TRACK',
                    'AT RISK' => 'AT RISK',
                    'OVER BUDGET' => 'OVER BUDGET',
                    'COMPLETED' => 'COMPLETED',
                ],
                'attr' => ['class' => 'form-select select2', 'data-toggle' => 'select2'],
                'disabled' => true,
                'label' => 'Budget Status'
            ])
            ->add('dueDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'data-provider' => 'flatpickr',
                    'data-date-format' => 'Y-m-d'
                ],
                'label' => 'Due Date'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectBudget::class,
            'fiscal_start' => null,
            'fiscal_end' => null,
        ]);
    }
}
