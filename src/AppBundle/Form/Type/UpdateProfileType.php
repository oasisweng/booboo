<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class UpdateProfileType extends AbstractType
{
    public function buildForm( FormBuilderInterface $builder, array $options ) {
        $builder->add( 'name', TextType::class )
        ->add( 'save', SubmitType::class);
    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'data_class' => 'AppBundle\Entity\User'
            ) );
    }
}
?>
