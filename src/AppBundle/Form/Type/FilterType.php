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
        ->add('price_ascending',ChoiceType::class, array(
            'label'    => 'Price',
            'label_attr' => array('style'=>'text-align:left;'),
            'required' => false,
            'choices'  => array(
                'High to Low' => false,
                'Low to High' => true
                
            ),
            'choices_as_values' => true,
            'multiple' => false,
            'expanded' => true,
            'data'=> false
        ))
        ->add('created_ascending', ChoiceType::class, array(
            'label'    => 'Ending Time',
            'label_attr' => array('style'=>'text-align:left;'),
            'required' => false,
            'choices'  => array(
                'Sooner First' => true,
                'Later First' => false
                
            ),
            'choices_as_values' => true,
            'multiple' => false,
            'expanded' => true,
            'data'=> true
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
