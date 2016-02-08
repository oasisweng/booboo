<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

class ForgottenPasswordType extends AbstractType
{
  public function buildForm( FormBuilderInterface $builder, array $options ) {
    $builder->add('password', PasswordType::class)
    ->add( 'newPassword', RepeatedType::class, array(
        'type' => PasswordType::class,
        'first_options'  => array( 'label' => 'New password' ),
        'second_options' => array( 'label' => 'Repeat new password' ),
      ))
    ->add( 'save', SubmitType::class);
  }

  public function configureOptions( OptionsResolver $resolver ) {
    $resolver->setDefaults( array(
        'data_class' => 'AppBundle\Entity\User'
      ) );
  }
}
?>
