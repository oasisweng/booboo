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
    private $name;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\Length(max = 12)
     */
    private $password;

}
?>
