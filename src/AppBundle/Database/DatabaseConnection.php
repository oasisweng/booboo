<?php

namespace AppBundle\Database;
use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class DatabaseConnection {
  private $container;

  public function __construct( Container $container ) {
    $this->container = $container;
  }


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
    $safe_id = mysqli_real_escape_string( $connection, $id );
    $query = "SELECT * FROM {$column} WHERE id = {$safe_id} LIMIT 1";
    $result = mysqli_query( $connection, $query );
    $object = mysqli_fetch_assoc( $result );
    if ( !is_null( $object ) ) {
      return $object;
    } else {
      return false;
    }
  }

  public function deleteOne( $connection, $column, $id ) {
    $safe_id = mysqli_real_escape_string( $connection, $id );
    $query = "DELETE FROM item WHERE id ={$safe_id} LIMIT 1";
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
    if ( isset( $image ) ) {
      $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/uploads/photos/';
      $generator = new SecureRandom();
      $fileName = $generator->nextBytes( 10 );
      $image->move( $dir, $fileName );
      $imageURL = $fileName;
      $escaped_imageURL = $this->e( $connection, $imageURL );
    }
    $query = "INSERT INTO item (itemName,description,imageURL, categoryID) " .
      "VALUES ('{$escaped_name}','{$escaped_description}',"  .
      ( isset( $escaped_imageURL ) ? "'{$escaped_imageURL}'" : "NULL" ) .
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
    if ( isset( $image )) {
      $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/uploads/photos/';
      //remove old one
      $fs = new Filesystem();
      if ( $fs->exists( $dir.$imageURL ) ) {
        $fs->remove( $dir.$imageURL );
      }

      //create new one
      $generator = new SecureRandom();
      $fileName = $generator->nextBytes( 10 );
      $image->move( $dir, $fileName );
      $imageURL = $fileName;
      $escaped_imageURL = $this->e( $connection, $imageURL );
    }

    $query = "UPDATE item SET ".
      "itemName='{$escaped_name}',".
      "description='{$escaped_description}',".
      "imageURL=" . ( isset( $escaped_imageURL ) ? "'{$escaped_imageURL}'" : "NULL" ) . "," .
      "categoryID={$categoryID} " .
      "WHERE id={$id}";

    $result = mysqli_query( $connection, $query );
    if ( $result && mysqli_affected_rows( $connection ) >= 0 ) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  public function addAuction( $connection, $auction ) {
    $sellerID = $auction->sellerID;
    $startAt = $auction->startAt->format( 'Y-m-d H:i:s' );
    $endAt = $auction->endAt->format( 'Y-m-d H:i:s' );
    $item  = $auction->item;
    $startingBid = $auction->startingBid;
    $minBidIncrease = $auction->minBidIncrease;
    $reservedPrice = $auction->reservedPrice;

    // save item
    $itemId = $this->addItem( $connection, $item );

    $query =  "INSERT INTO auction ";
    $query .= "(sellerID,startAt,endAt, itemId, startingBid, minBidIncrease, reservedPrice) ";
    $query .= "VALUES ({$sellerID},'{$startAt}','{$endAt}',{$itemId},{$startingBid},";
    $query .= ( isset( $minBidIncrease ) ? "{$minBidIncrease}" : "NULL" ) . ",";
    $query .= ( isset( $reservedPrice ) ? "{$reservedPrice}" : "NULL" ) . ")";

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
    $startAt = $auction->startAt->format( 'Y-m-d H:i:s' );
    $endAt = $auction->endAt->format( 'Y-m-d H:i:s' );
    $item  = $auction->item;
    $startingBid = $auction->startingBid;
    $minBidIncrease = $auction->minBidIncrease;
    $reservedPrice = $auction->reservedPrice;

    $query = "UPDATE auction SET ".
      "startAt='{$startAt}',".
      "endAt='{$endAt}',".
      "startingBid={$startingBid},".
      "minBidIncrease={$minBidIncrease}," .
      "reservedPrice={$reservedPrice} " .
      "WHERE id={$id}";


    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      // save item
      if ($this->updateItem( $connection, $item )){
        return true;
      } else {
        return false;
      }
    } else {
      die( "Database query failed (Auction). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function addUser( $user ) {
    $connection = $this->connect();
    $name = $user->name;
    $email = $user->email;
    $password = $user->password;

    //encrptyion
    $encrptyed_password = encrpt( $pasword );

    $query = "INSERT INTO user ".
      "(name,email,password) ".
      "VALUES({$name},{$email},{$encrptyed_password})";

    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      return $id;
    } else {
      die( "Database query failed (User). " . mysqli_error( $connection ) );
      return FALSE;
    }

  }

  public function updateUser($connection, $user){
    $id = $user->id;
    $name = $user->name;
    $email = $user->email;
    $password = $user->password;
    // reset password
    if (isset($password) && $this->login($email)){
      $password = encrpt( $password );
      $query = "UPDATE user SET";
      $query .= "password='{$password}' ";
      $query .= "WHERE id = {$id}"
    } else {
      $query = "UPDATE user SET";
      $query .= "name='{$name}',";
      $query .= "email='{$email}' ";
      $query .= "WHERE id={$id}";
    }

    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      // save item
      if ($this->updateItem( $connection, $item )){
        return true;
      } else {
        return false;
      }
    } else {
      die( "Database query failed (UpdateUser). " . mysqli_error( $connection ) );
      return FALSE;
    }
    



  }

  public function login( $user ) {
    $connection = $this->connect();
    $email = mysqli_real_escape_string( $user->email );
    $query = "SELECT * FROM user WHERE email={$email} LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $row = mysqli_fetch_assoc($result);
      if (check_password($user->password, $row["password"])){
        $id =  mysqli_insert_id( $connection );
        return $id;
      } else {
        return false;
      }
    } else {
      die( "Database query failed (User login). " . mysqli_error( $connection ) );
      return false;
    }
  }

  public function fetchCategories() {
    $con = $this->connect();
    $query = "SELECT * FROM category ORDER BY id ASC LIMIT 25";
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


  private function encrpt( $string ) {
    $hash_format = "$2$11$";
    $salt = buy_salt( 22 );
    $formatted_salt = $hash_format . $salt;
    return crypt( $password, $formatted_salt );
  }

  private function check_password( $password, $hashed_password ) {
    $hash = crypt( $password, $hashed_password );
    if ( $hash==$hashed_password ) {
      return true;
    } else {
      return false;
    }
  }

  private function buy_salt( $length ) {
    $unique_random_string = md5( uniqid( mt_rand(), true ) );
    $base64_string = base64_encode( $unique_random_string );
    $modified_base64_string = str_replace( '+', '.', $base64_string );
    $salt = substr( $modified_base64_string, 0, $length );
    return $salt;

  }
}

?>
