<?php

namespace App\Form\FinanicalAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fiscal_year')
            ->add('budget_disposable')
            ->add('total_expense')
            ->add('margin_profit')
            ->add('base_currency')
            ->add('start_date')
            ->add('end_date')
            ->add('status')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BudgetProfile::class,
        ]);
    }
}
