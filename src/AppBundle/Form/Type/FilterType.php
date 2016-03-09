<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Form\Type\CategoryType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class FilterType extends AbstractType
{
    public function buildForm( FormBuilderInterface $builder, array $options ) {
        $builder->add( 'categories', CategoryType::class, array(
                'label' => 'Filter Category',
                'label_attr' => array('style'=>'text-align:left;'),
                'multiple' => true,
                'expanded' => true
            ))
        ->add('order',ChoiceType::class, array(
            'label'    => 'Search Order',
            'label_attr' => array('style'=>'text-align:left;'),
            'required' => false,
            'choices'  => array(
                'Price Low to High' => 1,
                'Price High to Low' => 2,
                'Ending Sooner First' => 3,
                'Ending Later First' => 4
                
            ),
            'choices_as_values' => true,
            'multiple' => false,
            'data'=> 1
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
