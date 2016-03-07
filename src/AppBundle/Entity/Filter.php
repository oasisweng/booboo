<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 */
class Filter {
    /**
     * filter categories
     */
    public $categories;

    public $price_ascending;

    public $created_ascending;


    public function __construct() {
      $categories = NULL;

      $price_ascending = false;

      $created_ascending = false;
    }
}
?>
