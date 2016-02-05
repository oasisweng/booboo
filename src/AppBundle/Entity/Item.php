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

    public function __construct( $item ) {
        $this->id = $item->id;
        $this->itemName = $item->itemName;
        $this->description = $item->description;
        if (isset($item->imageURL)){
            $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/uploads/photos/';
            $this->image = new File(($dir . $item->imageURL));
            $this->imageURL = $this->imageURL;
        } else {
            $this->image = NULL; 
            $this->imageURL = NULL;
        }
        $this->ownerID = $item->ownerID;
        $this->categoryID = $item->categoryID;
    }
}
?>
