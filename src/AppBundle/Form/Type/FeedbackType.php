<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class FeedbackType extends AbstractType
{
  public function buildForm( FormBuilderInterface $builder, array $options ) {
    $builder->add( 'rating', ChoiceType::class, array(
        'choices'  => array(
          '1' => 1,
          '2' => 2,
          '3' => 3,
          '4' => 4,
          '5' => 5
        ), 'expanded' => true ) )
    ->add( 'comment', TextareaType::class)
    ->add( 'finish', SubmitType::class );

  }

  public function configureOptions( OptionsResolver $resolver ) {
    $resolver->setDefaults( array(
        'data_class' => 'AppBundle\Entity\Feedback',
      ) );
  }
}

?>
