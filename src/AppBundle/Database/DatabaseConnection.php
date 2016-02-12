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
    $dbhost = "127.0.0.1";
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
    if ( $result ) {
      $object = mysqli_fetch_assoc( $result );
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
    if ( isset( $image ) ) {
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
      $item->imageURL = $fileName;
      $imageURL = $fileName;
    }
    $escaped_imageURL = $this->e( $connection, $imageURL );

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
      die( "Database query failed (Auction Add). " . mysqli_error( $connection ) );
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
    $winnerID = $auction->winnerID;
    $ended = $auction->ended ? 1:0;
    $currentBid = $auction->currentBid;

    $query = "UPDATE auction SET ".
      "startAt='{$startAt}',".
      "endAt='{$endAt}',".
      "startingBid={$startingBid},".
      "minBidIncrease={$minBidIncrease}," .
      "reservedPrice={$reservedPrice}," .
      "winnerID={$winnerID}," .
      "ended={$ended}, " .
      "currentBid={$currentBid} " .
      "WHERE id={$id}";

    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      // save item
      if (!isset($item)){
        //special update which does not effect item
        return true;
      }

      if ( $this->updateItem( $connection, $item ) ) {
        return true;
      } else {
        return false;
      }
    } else {
      die( "Database query failed (Auction Update). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function shouldFinishAuction( $auction ) {
    // usleep(mt_rand(100000,1000000))
    // check if auction has finished
    $now = date( "Y-m-d H:i:s" );
    if ( $auction->endAt<$now ) {
      //if it should finish, check if it did finish
      if ( !$auction->ended ) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  public function finishAuction( $connection, $auction ) {
    $id = mysqli_real_escape_string( $connection, $auction->id );
    //get two highest bid(sort by value DESC and time ASC)
    $query = "SELECT buyerID FROM bid WHERE ";
    $query .= "auctionID={$id} ";
    $query .= "ORDER BY bidValue DESC, createdAt ASC ";
    $query .= "LIMIT 1";
    $result = mysqli_query( $connection, $query );
    $object = mysqli_fetch_assoc( $result );
    if ( $object ) {
      $auction->winnerID = $object["buyerID"];
    }
    $auction->ended = true;
    $this->updateAuction( $connection, $auction );
  }

  /*
   * get hot auction, which are bidden the most, for homepage
   * hot auction should not be expired
   */
  public function getHotAuctions($connection,$limit){
    $auctions = [];

    $query = "SELECT ";
    $query .= "auction.id, ";
    $query .= "item.imageURL ";
    $query .= "FROM ";
    $query .= "auction ";
    $query .= "INNER JOIN ";
    $query .= "(SELECT ";
    $query .= "auctionID, ";
    $query .= "COUNT(*) AS ct ";
    $query .= "FROM ";
    $query .= "bid ";
    $query .= "GROUP BY ";
    $query .= "bid.auctionID ";
    $query .= "ORDER BY ";
    $query .= "ct DESC ";
    $query .= "LIMIT 10 ";
    $query .= ") AS hot_bid ON hot_bid.auctionID = auction.id ";
    $query .= "INNER JOIN ";
    $query .= "item ON item.id = auction.itemID ";
    $query .= "WHERE  ";
    $query .= "auction.endAt>NOW() ";
    $query .= "ORDER BY ";
    $query .= "hot_bid.ct DESC ";
    $query .= "LIMIT 10 ";

    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (expiring auction). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

  /*
   * get new auction for homepage, defined by the date of creation
   */
  public function getNewAuctions($connection,$limit){
    $auctions = [];

    //get expiring auctions, ordered by how close they are to the end;
    $query ="SELECT ";
    $query .="auction.id, ";
    $query .="item.imageURL ";
    $query .="FROM ";
    $query .="auction ";
    $query .="INNER JOIN ";
    $query .="item ON item.id = auction.itemID ";
    $query .="WHERE ";
    $query .="auction.endAt>NOW() ";
    $query .="ORDER BY ";
    $query .="auction.createdAt DESC ";
    $query .="LIMIT 10 ";

    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (expiring auction). " . mysqli_error( $connection ) );
    }

    return $auctions;

  }

  /*
   * get expiring auction for homepage
   */
  public function getExpiringAuctions($connection,$limit){
    $auctions = [];

    //get expiring auctions, ordered by how close they are to the end;
    $query ="SELECT ";
    $query .="auction.id, ";
    $query .="item.imageURL ";
    $query .="FROM ";
    $query .="auction ";
    $query .="INNER JOIN ";
    $query .="item ON item.id = auction.itemID ";
    $query .="WHERE ";
    $query .="auction.endAt>NOW() ";
    $query .="ORDER BY ";
    $query .="auction.endAt ASC ";
    $query .="LIMIT 10 ";

    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (expiring auction). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

  public function getRandomAuctions($connection,$limit){
    $auctions = [];
    $query = "SELECT COUNT(*) as ct FROM auction";
    $result = mysqli_query($connection, $query);
    if ($result){
      //get count
      $countEntry = mysqli_fetch_assoc($result);
      $count = $countEntry["ct"];
      //get random auctions
      $rand_a = [];
      for ($i=0;$i<$limit;$i++){
        $rand_a[] = mt_rand(1,$count);
      }
      $rand_s = implode(",",$rand_a);

      $query = "SELECT ";
      $query .= "auction.id, ";
      $query .= "item.imageURL ";
      $query .= "FROM ";
      $query .= "auction ";
      $query .= "INNER JOIN ";
      $query .= "item ON item.id = auction.itemID ";
      $query .= "WHERE ";
      $query .= "auction.id IN({$rand_s}); ";

      $result = mysqli_query($connection,$query);
      if ($result){
        while ($row = mysqli_fetch_assoc($result)){
          $auctions[] = $row;
        }
      }else {
        die( "Database query failed (random auction). " . mysqli_error( $connection ) );
      }
    } else {
      die( "Database query failed (random auction). " . mysqli_error( $connection ) );
    }
 
    return $auctions;
  }

  public function getRecommendedAuctions($connection,$userID){
    //select all distinct categories user has bidded，ordered by number of categories
    //if results are not adequate, it will further select hottest auctions
    //in an attempt to attract user to bid them
    define("MAX_TOTAL_RECOMMENDATIONS",10);
    $query = "SELECT ";
    $query .= "auction.id,";
    $query .= "item.imageURL";
    $query .= "FROM ";
    $query .= "auction ";
    $query .= "INNER JOIN ";
    $query .= "item ON item.id = auction.itemID ";
    $query .= "INNER JOIN ";
    $query .= "( ";
    $query .= "SELECT ";
    $query .= "item.categoryID AS CategoryID, ";
    $query .= "COUNT(item.id) AS Occurrence ";
    $query .= "FROM ";
    $query .= "item, ";
    $query .= "( ";
    $query .= "SELECT ";
    $query .= "auction.itemID AS ItemID ";
    $query .= "FROM ";
    $query .= "auction, ";
    $query .= "( ";
    $query .= "SELECT DISTINCT ";
    $query .= "bid.auctionID AS AuctionID ";
    $query .= "FROM ";
    $query .= "bid ";
    $query .= "WHERE ";
    $query .= "bid.buyerID = {$userID} ";
    $query .= ") AS ab ";
    $query .= "WHERE ";
    $query .= "auction.id = ab.AuctionID ";
    $query .= ") AS ai ";
    $query .= "WHERE ";
    $query .= "item.id = ai.ItemID ";
    $query .= "GROUP BY ";
    $query .= "item.categoryID ";
    $query .= ") AS item_category ON item.categoryID = item_category.CategoryID ";
    $query .= "WHERE ";
    $query .= "item.imageURL IS NOT NULL ";
    $query .= "ORDER BY ";
    $query .= "item_category.Occurrence DESC, ";
    $query .= "auction.id DESC ";
    $query .= "LIMIT 10; ";

    $result = mysqli_query( $connection, $query );

    $auctions = [];
    //get reco auctions
    if ( $result ) {
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (get recommended auction). " . mysqli_error( $connection ) );
    }

    //if reco auctions are not adequate, get random auctions
    $reco_count = count($auctions);
    if ($reco_count<=constant("MAX_TOTAL_RECOMMENDATIONS")){
        $more = constant("MAX_TOTAL_RECOMMENDATIONS")-$reco_count;
        $random = $this->getRandomAuctions($connection,$more);
        array_push($auctions,$random);
      }

    return $auctions;
  }

  public function addBid( $connection, $bid ) {
    $bidValue = $bid->bidValue;
    $buyerID = $bid->buyerID;
    $auctionID = $bid->auctionID;

    $query = "INSERT INTO bid (bidValue,buyerID,auctionID) ";
    $query .= "VALUES({$bidValue},{$buyerID},{$auctionID})";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      return $id;
    } else {
      die( "Database query failed (Bid). " . mysqli_error( $connection ) );
      return false;
    }
  }

  public function bid( $connection, $bid, $auction ) {
    //check if auction is on,
    echo "<pre>";
    var_dump($bid);
    var_dump($auction);
    echo "<pre/>";
    $now = date( "Y-m-d H:i:s" );
    if ( $auction->endAt>$now ) {
      //check if currentBid is null
      if ( is_null( $auction->currentBid ) ) {
        //save the bid and change current bid to the min value and winnerId to userId and return true and congrats
        if ( $bid->id = $this->addBid( $connection, $bid ) ) {
          $auction->currentBid = $auction->startingBid;
          $auction->winnerID = $bid->buyerID;
          $this->updateAuction( $connection, $auction );
          return array( "status"=>true, "message"=>"Congratulations" );
        } else {
          return array( "status"=>false, "message"=>"Unable to get records" );
        }
      } else {
        //check if bid is higher than currentBid+minInc
        if ( $bid->bidValue>( $auction->currentBid+$auction->minBidIncrease ) ) {
          //save the current bid
          if ( $bid->id=$this->addBid( $connection, $bid ) ) {
            //get two highest bid(sort by value DESC and time ASC)
            $auctionID = $auction->id;
            $query = "SELECT * FROM bid WHERE ";
            $query .= "auctionID={$auctionID} ";
            $query .= "ORDER BY bidValue DESC, createdAt ASC ";
            $query .= "LIMIT 2";
            $result = mysqli_query( $connection, $query );
            $highestBid = mysqli_fetch_assoc( $result );
            $secondHighestBid = mysqli_fetch_assoc( $result );
            if ( is_null( $highestBid ) || is_null( $secondHighestBid ) ) {
              //return server error
              return array( "status"=>false, "message"=>"Unable to get records" );
            }
            //check if the bid is highest bid
            if ( $bid->id == $highestBid["id"] ) {
              //change current bid to the bid and winnerId to userId and return true and congrats
              if ($highestBid["bidValue"]>$secondHighestBid["bidValue"]+$auction->minBidIncrease){
                $auction->currentBid = $secondHighestBid["bidValue"]+$auction->minBidIncrease;
              } else {
                $auction->currentBid = $highestBid["bidValue"];
              }
              $auction->winnerID = $bid->buyerID;
              $this->updateAuction( $connection, $auction );
              return array( "status"=>true, "message"=>"Congratulations" );
            } else {
              // change current bid to the second highest bid and return false and report price outbid.
              $auction->currentBid = $secondHighestBid["bidValue"];
              $auction->winnerID = $highestBid["buyerID"];
              $this->updateAuction( $connection, $auction );
              return array( "status"=>false, "message"=>"Price outbid" );
            }

          } else {
            //return server error
            return array( "status"=>false, "message"=>"Unable to save records" );
          }
        } else {
          //return false and report price too low
          return array( "status"=>false, "message"=>"Price too low" );
        }

      }
    } else {
      //return false and report auction is over.
      return array( "status"=>false, "message"=>"Auction is over" );
    }
  }

  public function bidded( $connection, $auctionID, $userID ) {
    $query = "SELECT COUNT(*) as totalno FROM bid ";
    $query .= "WHERE auctionID={$auctionID},buyerID={$userID}";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $count = mysqli_fetch_assoc( $result );
      if ( $count["totalno"]>=0 ) {
        return true;
      } else {
        return false;
      }
    }else {
      return false;
    }

  }

  public function addUser( $user ) {
    $connection = $this->connect();
    $name = $user->name;
    $email = $user->email;
    $password = $user->password;

    //encrptyion
    $encrptyed_password = $this->encrpt( $password );

    $query = "INSERT INTO user ".
      "(name,email,password) ".
      "VALUES('{$name}','{$email}','{$encrptyed_password}')";

    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      return $id;
    } else {
      die( "Database query failed (User). " . mysqli_error( $connection ) );
      return FALSE;
    }

  }

  public function updateUser( $connection, $user ) {
    $id = $user->getId();
    $name = $user->name;
    $email = $user->email;
    $password = $user->password;
    $newPassword = $user->newPassword;
    // reset password
    if ( !is_null( $email ) && $this->login( $user ) ) {
      if ( !is_null( $newPassword ) ) {
        $newPassword = $this->encrpt( $newPassword );
        $query = "UPDATE user SET ";
        $query .= "password='{$newPassword}' ";
        $query .= "WHERE id = {$id}";
      } else {
        $query = "UPDATE user SET ";
        $query .= "name='{$name}' ";
        $query .= "WHERE id={$id}";
      }

      $result = mysqli_query( $connection, $query );
      $affected = mysqli_affected_rows( $connection );
      if ( $result && $affected >= 0 ) {
        return true;
      } else {
        die( "Database query failed (UpdateUser). " . mysqli_error( $connection ) );
        return false;
      }
    } else {
      return false;
    }

  }

  public function login( $user ) {
    $connection = $this->connect();
    $name = mysqli_real_escape_string( $connection, $user->name );
    $query = "SELECT * FROM user WHERE name='{$name}' LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $fetched_user = mysqli_fetch_assoc( $result );
      if ( $user->password == $fetched_user["password"] || $this->check_password( $user->password, $fetched_user["password"] ) ) {
        return $fetched_user["id"];
      } else {
        die( "Database query failed (User login). " . mysqli_error( $connection ) );
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


  private function encrpt( $password ) {
    $hash_format = "$2y$10$";
    $salt = $this->buy_salt( 22 );
    $formatted_salt = $hash_format . $salt;
    return crypt( $password, $formatted_salt );
  }

  private function check_password( $password, $hashed_password ) {
    $hash = crypt( $password, $hashed_password );
    // echo "{$password}<br/>";
    // echo "{$hashed_password}<br/>";
    // echo "{$hash}<br/>";

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
