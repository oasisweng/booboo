<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 */
class Bid {
    /**
     */
    public $id;

    /**
     *
     *
     *
     */
    public $bidValue;

    /**
     */
    public $buyerID;

    /**
     */
    public $auctionID;

    /**
     *
     *
     *
     * @Assert\DateTime()
     */
    public $createdAt;

    public function __construct( $bid = NULL ) {
        if ( isset( $bid ) ) {
            $this->id = $bid["id"];
            $this->bidValue = $bid["bidValue"];
            $this->buyerID = $bid["buyerID"];
            $this->auctionID = $bid["auctionID"];
            $this->createdAt = strtotime( $bid["createdAt"] );
        }
    }
}
?>
