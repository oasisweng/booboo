<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 */
class Feedback {

    public $id;

    public $auctionID;

    public $giverID;

    public $receiverID;

    /*
     * @Assert\Range(
     *      min = 1,
     *      max = 10
     * )
     */
    public $rating;

    public $comment;


    public function __construct( $feedback = NULL) {
        if (isset($feedback)){
            $this->id = $feedback["id"];
            $this->auctionID = $feedback["auctionID"];
            $this->giverID = $feedback["giverID"];
            $this->receiverID = $feedback["receiverID"];
            $this->rating = $feedback["rating"];
            $this->comment = $feedback["comment"];
        }
    }
}
?>
