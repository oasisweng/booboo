<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 */
class Feedback {

    public $id;

    public $auctionID;

    public $giverID;

    public $giverName;

    public $receiverID;

    public $receiverName;

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
            $this->auctionID = $feedback["auctionID"];
            $this->giverID = $feedback["giverID"];
            $this->giverName = empty($feedback["giverName"]) ? NULL : $feedback["giverName"] ;
            $this->receiverID = $feedback["receiverID"];
            $this->receiverName = empty($feedback["receiverName"]) ? NULL : $feedback["receiverName"] ;
            $this->rating = $feedback["rating"];
            $this->comment = $feedback["comment"];
            $this->id = $this->auctionID . "-" . $this->giverID;
        }
    }
}
?>
