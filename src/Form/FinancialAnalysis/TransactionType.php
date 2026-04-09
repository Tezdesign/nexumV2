<?php

namespace App\Form\FinancialAnalysis;

use App\Entity\FinancialAnalysis\Transaction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. TX-123456'],
                'label' => 'Reference'
            ])
            ->add('cost', NumberType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. 500.00'],
                'label' => 'Cost'
            ])
            ->add('date_stamp', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'data-provider' => 'flatpickr',
                    'data-date-format' => 'Y-m-d'
                ],
                'label' => 'Transaction Date'
            ])
            ->add('expense_category', ChoiceType::class, [
                'choices' => [
                    'Hardware' => 'HARDWARE',
                    'Software' => 'SOFTWARE',
                    'Services' => 'SERVICES',
                    'Travel' => 'TRAVEL',
                    'Marketing' => 'MARKETING',
                    'Other' => 'OTHER',
                ],
                'placeholder' => 'Select a Category...',
                'attr' => ['class' => 'form-select select2', 'data-toggle' => 'select2'],
                'label' => 'Expense Category'
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Description of the transaction'],
                'label' => 'Description'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
        ]);
    }
}
