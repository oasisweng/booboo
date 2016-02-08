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
     * 
     * @Assert\Email()
     */
    public $email;

    /**
     *
     *
     * 
     */
    public $name;

    /**
     *
     *
     * 
     * 
     */
    public $password;

    public $newPassword;


    public function getName(){
        return $this->name;
    }

    public function setName($name){
        $this->name=$name;
    }

    public function getId(){
        return $this->id;
    }

    public function __construct( $u = NULL ) {
        if ( isset($u) ) {
            $this->id = $u["id"];
            $this->email = $u["email"];
            $this->name = $u["name"];
            $this->password = $u["password"];
        } 
    }
}
?>
