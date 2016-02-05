<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CategoryType extends AbstractType
{

    private $categories;

    public function __construct() {
        //get data from database
        $this->categories = $this->get( "db" )-> fetchCategories();
    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'choices' => $this->categories,
                'choices_as_values' => true
            ) );
    }

    public function getParent() {
        return ChoiceType::class;
    }
}

?>
