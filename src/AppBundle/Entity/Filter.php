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

    public $order;


    public function __construct() {
      $categories = NULL;

      $order = 1;
    }
}
?>
