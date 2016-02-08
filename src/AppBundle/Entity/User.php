<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 *
 */
class User 
{
    /**
     *
     *
     */
    private $id;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\Email()
     */
    public $email;

    /**
     *
     *
     * @Assert\NotBlank()
     */
    public $name;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\Length(max = 12)
     */
    public $password;

    public $newPassword;


    public function getName(){
        return $this->name;
    }

    public function setName($name){
        $this->name=$name;
    }

    public function __construct( $u = NULL ) {
        if ( isset($u) ) {
            $id = $u->id;
            $email = $u->email;
            $name = $u->name;
        } 
    }
}
?>
