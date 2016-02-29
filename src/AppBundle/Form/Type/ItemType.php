<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use AppBundle\Form\Type\CategoryType;

class ItemType extends AbstractType
{
    public function buildForm( FormBuilderInterface $builder, array $options ) {
        $builder->add( 'itemName', TextType::class,array(
            'required' => true,
            )  )
        ->add( 'description', TextareaType::class, array(
            'required' => false,
        ) )
        ->add( 'image', FileType::class, array(
            'required' => true,
            ) )
        ->add( 'categoryID', CategoryType::class, array(
                'placeholder' => 'Choose a category',
                'label' => 'Category',
            ) );

    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'data_class' => 'AppBundle\Entity\Item',
            ) );
    }
}

?>
