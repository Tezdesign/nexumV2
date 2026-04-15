<?php

namespace App\Form\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\FinancialAnalysis\ProjectBudget;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpenseDraftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $projectId = $options['project_id'] ?? null;

        $builder
            ->add('subject', TextType::class, [
                'label' => 'Subject',
                'attr' => ['placeholder' => 'Brief subject of the expense']
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Amount',
                'currency' => false,
                'html5' => true,
                'attr' => ['placeholder' => '0.00', 'step' => '0.01']
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Category',
                'choices' => array_combine(
                    array_map('ucfirst', array_map('strtolower', ExpenseDraft::getValidCategories())),
                    ExpenseDraft::getValidCategories()
                ),
                'placeholder' => 'Select a Category...',
                'attr' => ['class' => 'form-select select2', 'data-toggle' => 'select2']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 3, 'placeholder' => 'Detailed description']
            ])
            ->add('project_budget_related', EntityType::class, [
                'class' => ProjectBudget::class,
                'choice_label' => 'name',
                'label' => 'Project Budget',
                'placeholder' => 'Select a budget',
                'query_builder' => function (EntityRepository $er) use ($projectId) {
                    $qb = $er->createQueryBuilder('pb');
                    if ($projectId) {
                        $qb->where('pb.project = :projectId')
                           ->setParameter('projectId', $projectId);
                    }
                    return $qb->orderBy('pb.name', 'ASC');
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseDraft::class,
            'project_id' => null,
        ]);
    }
}
