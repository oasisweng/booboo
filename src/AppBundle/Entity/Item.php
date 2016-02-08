<?php
namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 *
 */
class Item 
{
    /**
     *
     *
     */
    public $id;

    /**
     *
     *
     * @Assert\NotBlank()
     */
    public $itemName;

    /**
     *
     *
     * 
     */
    public $description;

    /**
     *
     * @Assert\Image(
     *     minWidth = 200,
     *     maxWidth = 400,
     *     minHeight = 200,
     *     maxHeight = 400
     *     )
     * 
     */
    public $image;

    public $imageURL;

    /**
     *
     *
     * 
     * 
     */
    public $ownerID;

    /**
     *
     * @Assert\NotNull()
     *
     */
    public $categoryID;

    /**
     *
     *
     * @Assert\NotBlank()
     * @Assert\DateTime()
     */
    public $createdAt;

    public function __construct( $item = NULL) {
        if (isset($item)){
        $this->id = $item["id"];
        $this->itemName = $item["itemName"];
        $this->description = $item["description"];
        $this->imageURL = isset($item["imageURL"]) ? $item["imageURL"] : NULL;
        $this->ownerID = $item["ownerID"];
        $this->categoryID = $item["categoryID"];
    }
    }
}
?>
