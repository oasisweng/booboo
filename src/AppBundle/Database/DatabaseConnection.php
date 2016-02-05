<?php

namespace AppBundle\Database;
use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class DatabaseConnection {
  public function connect() {
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = "root";
    $dbname = "comp3013_db";
    $connection = mysqli_connect( $dbhost, $dbuser, $dbpass, $dbname );
    if ( mysqli_connect_errno() ) {
      die( "Database connection failed: ".
        mysqli_connect_error() .
        " (" . mysqli_connect_errno() . ") "
      );
    }

    return $connection;
  }

  public function selectOne( $connection, $column, $id ) {
    $query = "SELECT * FROM {$column} WHERE id = ".(int)$id." LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      return mysqli_fetch_assoc( $result );
    } else {
      die( "Database query failed. " . mysqli_error( $connection ) );
    }
  }

  public function deleteOne( $connection, $column, $id ) {
    $query = "DELETE FROM item WHERE id = ".(int)$id." LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( !$result ) {
      die( "Database query failed. " . mysqli_error( $connection ) . "<br/>" );
      return FALSE;
    } else {
      return TRUE;
    }
  }

  public function addItem( $connection, $item ) {
    $itemName = $item->itemName;
    $description = $item->description;
    $image = $item->image;
    $categoryID = $item->categoryID;
    $escaped_name = $this->e( $connection, $itemName );
    $escaped_description = $this->e( $connection, $description );
    if ( isset($image) ) {
      $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/uploads/photos/';
      $generator = new SecureRandom();
      $fileName = $generator->nextBytes( 10 );
      $image->getData()->move( $dir, $fileName );
      $imageURL = $fileName;
      $escaped_imageURL = $this->e( $connection, $imageURL );
    }
    $query = "INSERT INTO item (itemName,description,imageURL, categoryID) " .
      "VALUES ('{$escaped_name}','{$escaped_description}',"  .
      ( isset($escaped_imageURL) ? "'{$escaped_imageURL}'" : "NULL" ) .
      ", {$categoryID})";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      return $id;
    } else {
      die( "Database query failed (Item). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function updateItem( $connection, $item ) {
    $id = $item->id;
    $itemName = $item->itemName;
    $description = $item->description;
    $image = $item->image;
    $categoryID = $item->categoryID;
    $imageURL = $item->imageURL;
    $escaped_name = $this->e( $connection, $itemName );
    $escaped_description = $this->e( $connection, $description );
    if ( isset($image) ) {
      $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/uploads/photos/';
      //remove old one
      $fs = new Filesystem();
      if ( $fs->exists( $dir.$imageURL ) ) {
        $fs->remove( $dir.$imageURL );
      }

      //create new one
      $generator = new SecureRandom();
      $fileName = $generator->nextBytes( 10 );
      $image->getData()->move( $dir, $fileName );
      $imageURL = $fileName;
      $escaped_imageURL = $this->e( $connection, $imageURL );
    }

    $query = "UPDATE item SET ".
    "itemName='{$escaped_name}',".
    "description='{$escaped_description}',".
    "imageURL=" . ( isset($escaped_imageURL) ? "'{$escaped_imageURL}'" : "NULL" ) . "," .
    "categoryID={$categoryID} " .
    "WHERE id={$id}";

    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      return TRUE;
    } else {
      die( "Database query failed (Item). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function addAuction( $connection, $auction ) {
    $sellerID = $auction->sellerID;
    $startAt = $auction->startAt;
    $endAt = $auction->endAt;
    $item  = $auction->item;
    $startingBid = $auction->startingBid;
    $minBidIncrease = $auction->minBidIncrease;
    $reservedPrice = $auction->reservedPrice;

    // save item
    $itemId = addItem( $connection, $item );

    $query = "INSERT INTO auction (sellerID,startAt,endAt, itemId, startingBid, minBidIncrease, reservedPrice) " .
      "VALUES ({$sellerID},{$startAt},{$endAt},{$itemId},{$startingBid},"  .
      ( isset($minBidIncrease) ? "{$minBidIncrease}" : "NULL" ) . "," .
      ( isset($reservedPrice) ? "{$reservedPrice}" : "NULL" ) . ")";

    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      return $id;
    } else {
      die( "Database query failed (Auction). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function updateAuction( $connection, $auction ) {
    $id = $auction->id;
    $startAt = $auction->startAt;
    $endAt = $auction->endAt;
    $item  = $auction->item;
    $startingBid = $auction->startingBid;
    $minBidIncrease = $auction->minBidIncrease;
    $reservedPrice = $auction->reservedPrice;

    // save item
    $itemId = updateItem( $connection, $item );

    $query = "UPDATE auction SET ".
    "startAt={$startAt},".
    "endAt={$endAt},".
    "startingBid={$startingBid},".
    "minBidIncrease=" . ( isset($minBidIncrease) ? "{$minBidIncrease}" : "NULL" ) . "," .
    "reservedPrice=" . ( isset($reservedPrice) ? "{$reservedPrice}" : "NULL" ) .
    "WHERE id={$id}";

    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      return TRUE;
    } else {
      die( "Database query failed (Auction). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function fetchCategories() {
    $con = $this->connect();
    $query = "SELECT * FROM category LIMIT 25";
    $result = mysqli_query( $con, $query );
    $categories = [];
    while ( $row = mysqli_fetch_assoc( $result ) ) {
      $categories[] = $row;
    }

    return $categories;
  }

  private function e( $connection, $string ) {
    return mysqli_real_escape_string( $connection, $string );
  }

}

?>
