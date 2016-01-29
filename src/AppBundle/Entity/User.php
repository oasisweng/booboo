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
    private $email;

    /**
     *
     *
     * @Assert\NotBlank()
     */
    private $username;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\Length(max = 12)
     */
    private $password;

    // other properties and methods
    public function getId($id){
        $this->id = $id;
    }

    public function setId(){
        return $this->id;
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail( $email ) {
        $this->email = $email;
    }

    public function getUsername() {
        return $this->username;
    }

    public function setUsername( $username ) {
        $this->username = $username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function setPassword( $password ) {
        $this->password = $password;
    }

}
?>
