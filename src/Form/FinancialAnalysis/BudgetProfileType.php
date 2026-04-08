<?php

namespace App\Form\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fiscal_year', TextType::class, [
                'attr' => ['class' => 'form-control', 'readonly' => true],
                'label' => 'Fiscal Year'
            ])
            ->add('budget_disposable', NumberType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Disposable Budget'
            ])
            ->add('base_currency', ChoiceType::class, [
                'choices' => [
                    'North America' => [
                        'US Dollar (USD)' => 'USD',
                        'Canadian Dollar (CAD)' => 'CAD',
                        'Mexican Peso (MXN)' => 'MXN',
                    ],
                    'Europe' => [
                        'Euro (EUR)' => 'EUR',
                        'British Pound (GBP)' => 'GBP',
                        'Swiss Franc (CHF)' => 'CHF',
                        'Swedish Franc (CHF)' => 'SEK',
                    ],
                    'Asia' => [
                        'Japanese Yen (JPY)' => 'JPY',
                        'Chinese Yuan (CNY)' => 'CNY',
                        'Indian Rupee (CNY)' => 'INR',
                        'South Korean Won (KRW)' => 'KRW',
                    ],
                    'Africa' => [
                        'Tunisian Dinar (TND)' => 'TND',
                        'South African Rand (ZAR)' => 'ZAR',
                        'Nigerian Naira (ZAR)' => 'NGN',
                    ],
                    'Oceania' => [
                        'Australian Dollar (AUD)' => 'AUD',
                        'New Zealand Dollar (NZD)' => 'NZD',
                    ]
                ],
                'attr' => ['class' => 'form-control select2', 'data-toggle' => 'select2'],
                'label' => 'Base Currency'
            ])
            ->add('start_date', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd',
                'attr' => ['class' => 'form-control', 'data-provider' => 'flatpickr', 'data-date-format' => 'Y-m-d'],
                'label' => 'Start Date'
            ])
            ->add('end_date', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'yyyy-MM-dd',
                'attr' => ['class' => 'form-control', 'data-provider' => 'flatpickr', 'data-date-format' => 'Y-m-d'],
                'label' => 'End Date'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BudgetProfile::class,
        ]);
    }
}
