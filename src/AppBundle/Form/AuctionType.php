<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use AppBundle\Form\ItemType;

class ItemType extends AbstractType
{
    public function buildForm( FormBuilderInterface $builder, array $options ) {
        $builder->add( 'sellerID', HiddenType::class )
        ->add( 'startAt', DateTimeType::class )
        ->add( 'endAt', DateTimeType::class )
        ->add( 'item', new ItemType())
        ->add( 'startingBid', MoneyType::Class , array(
            'grouping' => true) )
        ->add( 'minBidIncrease', MoneyType::Class , array(
            'grouping' => true,
            'required'    => false))
        ->add( 'reservedPrice', MoneyType::Class, array(
            'grouping' => true,
            'required'    => false) )
        ->add('save',SubmitType::class, array('label' => 'Start Auction'));

    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'data_class' => 'AppBundle\Entity\Item',
            ) );
    }
}

?>
