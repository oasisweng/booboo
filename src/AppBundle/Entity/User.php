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
    public $id;

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

    public $nameOrEmail;

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
