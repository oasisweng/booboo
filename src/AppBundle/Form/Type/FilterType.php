<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Form\Type\CategoryType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class FilterType extends AbstractType
{
    public function buildForm( FormBuilderInterface $builder, array $options ) {
        $builder->add( 'categories', CategoryType::class, array(
                'label' => 'Filter Category',
                'multiple' => true,
                'expanded' => true
            ))
        ->add('filter', SubmitType::class);

    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'data_class' => 'AppBundle\Entity\Filter',
            ) );
    }
}

?>
