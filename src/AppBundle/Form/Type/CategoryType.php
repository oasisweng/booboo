<?php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use AppBundle\Database\DatabaseConnection;

class CategoryType extends AbstractType
{

    private $categories;
    public function __construct(DatabaseConnection $db) {
        //get data from database
        $this->categories = $this->choicifyCategories($db-> fetchCategories());

    }

    public function configureOptions( OptionsResolver $resolver ) {
        $resolver->setDefaults( array(
                'choices' => $this->categories,
                'choices_as_values' => false
            ) );
    }

    public function getParent() {
        return ChoiceType::class;
    }

    private function choicifyCategories($categories){
        $choices = [];
        foreach ($categories as $category){
            $id = $category["id"];
            $choices[$id] = $category["categoryName"];
        }

        return $choices;
    }
}

?>
