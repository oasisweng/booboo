<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use \DateTime;

/**
 */
class Auction {
    /**
     */
    public $id;

    /**
     *
     *
     * @Assert\NotBlank()
     *
     */
    public $sellerID;

    /**
     */
    public $winnerID;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\DateTime()
     */
    public $startAt;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\DateTime()
     */
    public $endAt;

    /**
     */
    public $itemName;

    public $itemID;

    public $item;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\GreaterThanOrEqual(
     *     value = 1.0
     * )
     */
    public $startingBid;

    /**
     */
    public $minBidIncrease;

    /**
     */
    public $viewCount;

    /**
     */
    public $currentBid;

    /**
     *
     *
     * @Assert\DateTime()
     */
    public $createdAt;

    /**
     *
     *
     * @Assert\DateTime()
     */
    public $updatedAt;


    /**
     */
    public $reservedPrice;


    public $ended;

    public $bidValue;

    public $sellerName;

    public function __construct( $a = NULL ) {
        if ( isset($a) ) {
            $this->id = $a["id"];
            $this->sellerID = $a["sellerID"];
            if (isset($a["sellerName"])) {
                $this->sellerName = $a["sellerName"];
            }
            $this->winnerID = $a["winnerID"];
            $this->startAt = new DateTime($a["startAt"]);
            $this->endAt = new DateTime($a["endAt"]);
            $this->itemID = $a["itemID"];
            if (isset($a["itemName"])) {
                $this->itemName = $a["itemName"];
            }
            $this->viewCount = $a["viewCount"];
            $this->createdAt = new DateTime($a["createdAt"]);
            $this->updatedAt = new DateTime($a["updatedAt"]);
            $this->startingBid = $a["startingBid"];
            $this->minBidIncrease = $a["minBidIncrease"];
            $this->reservedPrice = $a["reservedPrice"];
            $this->ended = $a["ended"] ? true : false;
            $this->currentBid = $a["currentBid"];
            if (isset($a["bidValue"])) {
                $this->bidValue = $a["bidValue"];
            }
        } else {

            $this->startingBid = 1.0;
            $this->minBidIncrease = 0.5;
            $this->viewCount =  0;
            $this->createdAt = date( "Y-m-d H:i:s" );
            $this->updatedAt = date( "Y-m-d H:i:s" );
            $this->ended = false;
        }
    }

}
?>
