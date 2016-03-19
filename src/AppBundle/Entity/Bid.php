<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use \DateTime;

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

    public $buyerName;
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
            $this->buyerName = $bid["buyerName"];
            $this->auctionID = $bid["auctionID"];
            $this->createdAt = date("c", strtotime($bid["createdAt"]));
        }
    }
}
?>
