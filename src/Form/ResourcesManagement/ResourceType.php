<?php

namespace App\Form\ResourcesManagement;

use App\Entity\ResourcesManagement\Resource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ResourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('resource_name')
        ->add('resource_code')
        ->add('resource_type')
        ->add('unit_cost')
        ->add('total_quantity')
        ->add('image_path', FileType::class, [
            'mapped' => false,
            'required' => false,
        ]);
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Resource::class,
        ]);
    }
}
