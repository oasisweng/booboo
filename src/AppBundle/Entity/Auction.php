<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

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

    public function __construct( $a ) {
        $this->id = $a->id;
        $this->sellerID = $a->sellerID;
        $this->winnerID = $a->winnerID;
        $this->startAt = $a->startAt;
        $this->endAt = $a->endAt;
        $this->item = isset( $a->itemId ) ? ( new Item( $a->itemId ) ) : NULL;
        $this->startingBid = 1.0;
        $this->minBidIncrease = 0.5;
        $this->viewCount = isset( $a->viewCount ) ? $a->viewCount : 0;
        $this->createdAt = isset( $a->createdAt ) ? $a->createdAt : date( "Y-m-d H:i:s" );
        $this->updatedAt = isset( $a->updatedAt ) ? $a->updatedAt : date( "Y-m-d H:i:s" );
    }

}
?>
