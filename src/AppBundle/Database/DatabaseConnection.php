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

  // General
  
  public function connect() {
    if (in_array($this->container->getParameter('kernel.environment'),array('test','dev'))) {
      $dbhost = "127.0.0.1";
      $dbuser = "root";
      $dbpass = "root";
      $dbname = "comp3013_db";
    } else {
      $dbhost = "eu-cdbr-azure-north-d.cloudapp.net";
      $dbuser = "bcaee1cbd4c59b";
      $dbpass = "4a3a7ecd";
      $dbname = "comp3013_db";
    }
    
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
    $safe_id = $this->e( $connection, $id );
    $safe_column = $this->e($connection,$column);
    $query = "SELECT * FROM {$safe_column} ";
    $where = "WHERE {$column}.id = {$safe_id} ";
    $limit = "LIMIT 1";
    //handle auction special case WinnerID
    if ($column=="auction"){
      //winnerID
      $query .= "LEFT JOIN ( ";
      $query .= "SELECT bid.auctionID,b1.currentBid,bid.buyerID as winnerID ";
      $query .= "FROM bid ";
      $query .= "INNER JOIN ( ";
      $query .= "SELECT MAX(bid.bidValue) AS currentBid ";
      $query .= "FROM bid ";
      $query .= "WHERE bid.auctionID = {$safe_id} ";
      $query .= ") AS b1 ON bid.bidValue = b1.currentBid ";
      $query .= "WHERE bid.auctionID = {$safe_id} ";
      $query .= ") AS winner  ";
      $query .= "ON winner.auctionID={$safe_id} ";
    }
    //handle item special case ImageURL
    if ($column=="item"){
      $query .= "LEFT JOIN ";
      $query .= "itemimage ON itemimage.itemID={$safe_id} ";
    }

    //var_dump($query.$where.$limit);
    $result = mysqli_query( $connection, $query.$where.$limit );
    if ( $result ) {
      $object = mysqli_fetch_assoc( $result );
      return $object;
    } else {
      die( "Database query failed. " . mysqli_error( $connection ) . "<br/>" );
      return false;
    }
  }

  public function deleteOne( $connection, $column, $id ) {
    $safe_id = $this->e( $connection, $id );
    $query = "DELETE FROM item WHERE id ={$safe_id} LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( !$result ) {
      die( "Database query failed. " . mysqli_error( $connection ) . "<br/>" );
      return FALSE;
    } else {
      return TRUE;
    }
  }

  // Item

  public function addItem( $connection, $item ) {
    $itemName = $this->e($item->itemName);
    $ownerID = $this->e($item->ownerID);
    $description = isset($item->description) ? $this->e($item->description):"";
    $image = $this->e($item->image);
    $categoryID = $this->e($item->categoryID);

    $query = "INSERT INTO item (itemName,description,ownerID,categoryID) " .
      "VALUES ('{$itemName}','{$description}',{$ownerID},{$categoryID})";
    
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $id =  mysqli_insert_id( $connection );
      //save image if exist
      if ( isset( $image ) ) {
        $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/assets/photos/';
        $fileName = $this->generateRandomString().".".$image->guessExtension();
        $image->move( $dir, $fileName );
        $imageURL = $this->e($fileName);

        $query2 = "INSERT INTO itemimage (itemID,imageURL) ";
        $query2 .= "VALUES ({$id},'{$imageURL}')";

        $result2 = mysqli_query( $connection, $query2 );
        if ( $result2 ) {
          return $id;
        } else {
          die( "Database query failed (Item Image). " . mysqli_error( $connection ) );
        }
      }
      return $id;
    } else {
      die( "Database query failed (Item). " . mysqli_error( $connection ) );
      return FALSE;
    }
  }

  public function updateItem( $connection, $item ) {
    $id = $this->e($item->id);
    $itemName = $this->e($item->itemName);
    $description = $description = isset($item->description) ? $this->e($item->description):"";
    $image = $item->image;
    $categoryID = $this->e($item->categoryID);
    $imageURL = $this->e($item->imageURL);
    $query = "UPDATE item SET ";
    $query .="itemName='{$itemName}', ";
    $query .="description='{$description}', ";
    $query .="categoryID={$categoryID} ";
    $query .="WHERE id={$id}";
    $result = mysqli_query( $connection, $query );
    $rows = mysqli_affected_rows( $connection );
    if ( $result && $rows >= 0 ) {
      if ( isset( $image ) ) {
        $dir = $this->container->getParameter( 'kernel.root_dir' ).'/../web/assets/photos';
        //create new one
        $fileName = $this->generateRandomString().".".$image->guessExtension();
        $image->move( $dir, $fileName );
        $imageURL = $fileName;

        //image might not exist initially
        if (is_null($item->imageURL)){
          $query2 = "INSERT INTO itemimage (itemID,imageURL) ";
          $query2 .= "VALUES ({$id},'{$imageURL}')";
        } else {
          $query2 = "UPDATE itemimage SET ";
          $query2 .= "imageURL='{$imageURL}' ";
          $query2 .= "WHERE itemID={$id}";
        }

        $result2 = mysqli_query( $connection, $query2 );
        $rows2 = mysqli_affected_rows( $connection );
        if ( $result2 && $rows2 >= 0 ) {
          return array("status"=>"success","message"=>"");
        } else {
          return array("status"=>"danger","message"=>"image did not save");
        }
      }
      return array("status"=>"success","message"=>"");
    } else {
      return array("status"=>"danger","message"=>"item did not update");
    }
  }

  // Auction
  
  // @param $connection database connection object
  // @param $auctionID id of the subject auction
  // @param $columns_a an array of the columns to be retrieved
  public function selectAuctionColumns( $connection, $auctionID, $columns_a){
    $columns_s = implode(",",$columns_a);
    $query = "SELECT {$columns_s} FROM auction WHERE auction.id = {$auctionID} LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $object = mysqli_fetch_assoc( $result );
      return $object;
    } else {
      return false;
    }
  }

  public function addAuction( $connection, $auction ) {
    $sellerID = $auction->sellerID;
    $startAt = $auction->startAt->format( 'Y-m-d H:i:s' );
    $endAt = $auction->endAt->format( 'Y-m-d H:i:s' );
    $item  = $auction->item;
    $item->ownerID = $auction->sellerID;
    $startingBid = $auction->startingBid;
    $minBidIncrease = $auction->minBidIncrease;
    $reservedPrice = $auction->reservedPrice;

    // save item
    $itemId = $this->addItem( $connection, $item );

    $query =  "INSERT INTO auction ";
    $query .= "(sellerID,startAt,endAt, itemId, startingBid, minBidIncrease, reservedPrice,updatedAt) ";
    $query .= "VALUES ({$sellerID},'{$startAt}','{$endAt}',{$itemId},{$startingBid},";
    $query .= "{$minBidIncrease},";
    $query .= "{$reservedPrice},";
    $query .= "NOW())";

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
    $ended = $auction->ended ? 1:0;

    $query = "UPDATE auction SET ".
      "startAt='{$startAt}',".
      "endAt='{$endAt}',".
      "startingBid={$startingBid},".
      "minBidIncrease={$minBidIncrease}," .
      "reservedPrice={$reservedPrice}," .
      "ended={$ended}," .
      "updatedAt=NOW() " .
      "WHERE id={$id} ";
    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      // save item
      if (!isset($item)){
        //special update which does not effect item
        return true;
      }

      $response = $this->updateItem( $connection, $item );
      if ( $response["status"] == "success" ) {
        return true;
      } else {
        //var_dump($response["message"]);
        return false;
      }
    } else {
      die( "Database query failed (Auction Update). " . mysqli_error( $connection ) );
      return false;
    }
  }

  public function getAuctionsWithCategoryName($connection,$categoryName){

    $query = "SELECT auction.id,item.itemName,auction.sellerID,user.name,itemimage.imageURL FROM auction ";
    $query .= "INNER JOIN item ON item.id = auction.itemID  ";
    $query .= "INNER JOIN category ON category.id = item.categoryID ";
    $query .= "INNER JOIN itemimage ON itemimage.itemID = item.id ";
    $query .= "INNER JOIN user ON auction.sellerID = user.id ";
    $query .= "WHERE ";
    $query .= "category.categoryName = '{$categoryName}' ";
    $query .= "and auction.endAt > NOW() ";
    $query .= "ORDER BY ";
    $query .= "auction.createdAt DESC ";

    $auctions = [];
    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (getAuctionsWithCategoryName). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

// @return an array of each auction that has new bids and their item and user information
  public function countNewBids($connection){
    //count new bids for each non-ending auctions
    $query = "SELECT bid.auctionID,item.itemName,user.name,user.email,auction.updatedTo,COUNT(*) as ct FROM bid  ";
    $query .= "LEFT JOIN auction ON bid.auctionID = auction.id ";
    $query .= "LEFT JOIN item ON item.id = auction.itemID ";
    $query .= "LEFT JOIN user ON user.id = auction.sellerID ";
    $query .= "WHERE bid.createdAt>auction.updatedTo ";
    $query .= "GROUP BY bid.auctionID ";

    var_dump($query);

    $auctions = [];
    $auctionIDs = [];
    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
        $auctionIDs[] = $row['auctionID'];
      }

      // For demonstration, we allow no update on updatedTo column
      // if (count($auctionIDs)>0){
      //   $auctionIDs_s = implode(",",$auctionIDs);
      //   //update auction updatedTo
      //   $query2 = "UPDATE auction ";
      //   $query2 .= "SET auction.updatedTo = NOW() ";
      //   $query2 .= "WHERE auction.id in {$auctionIDs_s} ";
      //   $result2 = mysqli_query($connection,$query2);
      //   $affected = mysqli_affected_rows( $connection );
      //   if ( $result2 && $affected >= 0 ) {
      //     return $auctions;
      //   } else {
      //     die( "Database query failed (count new bids 2 ). " . mysqli_error( $connection ) );
      //     return false;
      //   }
      // } 
    }else {
      die( "Database query failed (count new bids 1). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

public function getPendingFinishedAuctions( $connection ) {
    $query = "SELECT id FROM auction where endAt <= NOW() and ended = 0";
    $result = mysqli_query( $connection, $query );
    $auctions = array();
    if ($result) {
      while ($object = mysqli_fetch_assoc( $result )) {
        $auctions[] = $object['id'];
      }
    }
    return $auctions;
  }

  public function finishAuction( $connection, $auction ) {
    $id = $this->e( $connection, $auction->id );
    //get two highest bid(sort by value DESC and time ASC)
    //
    $query = "SELECT bid.buyerID AS winnerID, bid.bidValue AS currentBid, bid.auctionID ";
    $query .= "FROM bid WHERE bid.auctionID = {$id} ORDER BY bid.bidValue DESC LIMIT 1";
    $result = mysqli_query( $connection, $query );
    $bid = mysqli_fetch_assoc( $result );
    //make sure the highest price is higher than $auction's reserved price
    //if the current price is lowre than reserved price, set the current to reserved price
    
    $auction->ended = true;
    $this->updateAuction( $connection, $auction );

    if (!$bid){
      //no bid
      return array("status"=>"warning","message"=>"no bid");
    } else if ($bid["currentBid"]<$auction->reservedPrice){
       //auction didnt receive enough price, aborted
       return array("status"=>"warning","message"=>"reserved price unmet","winnerID"=>$bid["winnerID"]);
    } else {
      $auction->winnerID = $bid["winnerID"];
      return array("status"=>"success","winnerID"=>$bid["winnerID"]);
    }
    
  }

  public function getWinnerForAuction($connection,$auctionID){

    $query = "SELECT buyerID AS winnerID, ";
    $query .= "auctionID ";
    $query .= "FROM bid  ";
    $query .= "WHERE ";
    $query .= "bid.auctionID={$auctionID} ";
    $query .= "ORDER BY bid.bidValue DESC, bid.createdAt ASC ";
    $query .= "LIMIT 1";

    $result = mysqli_query($connection,$query);
    if ($result){
      $winner = mysqli_fetch_assoc($result);


      if (isset($winner["winnerID"])) {
        return $winner["winnerID"];
      }
    }else {
      die( "Database query failed (get winner for auction). " . mysqli_error( $connection ) );
    }

    return -1;


  }

  /*
   * get hot auction, which are bidden the most, for homepage
   * hot auction should not be expired
   */
  public function getHotAuctions($connection,$limit){
    $auctions = [];

    $query ="SELECT ";
    $query .="auction.id, ";
    $query .="itemimage.imageURL, ";
    $query .="item.itemName ";
    $query .="FROM ";
    $query .="auction ";
    $query .="INNER JOIN ";
    $query .="item ON item.id = auction.itemID ";
    $query .="LEFT JOIN ";
    $query .="itemimage on item.id = itemimage.itemID ";
    $query .="WHERE auction.endAt > NOW() ";
    $query .="ORDER BY ";
    $query .="auction.viewCount DESC ";
    $query .="LIMIT 10 ";

    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (hot auction). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

  /*
   * search for auctions based on keywords
   * @return: {"auctions"=>search result array, "totalPages"=>total number of pages}
   */
  public function searchAuctions($connection,$keywords,$page,$perPage,$filter){
    if ($page==0){
      return NULL;
    } 

    $order=$filter->order;
    $filter_categories=$filter->categories;

    //init auctions
    $auctions = [];
    //get offset
    $offset = ($page-1)*$perPage;


    $query_limit = "LIMIT {$offset},{$perPage} ";

    //order
    //'Price Low to High' => 1,
    // 'Price High to Low' => 2,
    // 'Ending Sooner First' => 3,
    // 'Ending Later First' => 4

    $query_order = "ORDER BY currentBid ASC ";
    switch ($order) {
      case 1:
          $query_order = "ORDER BY currentBid ASC ";
          break;
      case 2:
          $query_order = "ORDER BY currentBid DESC ";
          break;
      case 3:
          $query_order = "ORDER BY auction.endAt ASC ";
          break;
      case 4:
          $query_order = "ORDER BY auction.endAt DESC ";
      break;
    }
    
    // var_dump($query_order);

    //if there is keywords, do keyword search
    $where = "WHERE auction.endAt>NOW() ";

    //apply filters first
    if (!empty($filter_categories)){
      $category_s = implode(",",$filter_categories);
      $where .= "AND item.categoryID in ({$category_s}) ";
    }

    $keywords_c = count($keywords);
    if ($keywords_c>0){
      $first_keyword = $keywords[0];
      $where .= "AND item.itemName LIKE '%{$first_keyword}%' ";
      //add second keyword and more
      for ($i = 1; $i<$keywords_c;$i++){
        $keyword = $keywords[$i];
        $where .= "OR item.itemName LIKE '%{$keyword}%' ";
      }


      //get all auction
      $query ="SELECT ";
      $query .= "auction.id, ";
      $query .= "itemimage.imageURL, ";
      $query .= "item.itemName, ";
      $query .= "user.name, ";
      $query .= "auction.sellerID, ";
      $query .= "IfNull(currentBid.currentBid, auction.startingBid) AS currentBid, ";
      $query .= "auction.endAt ";
      $query .= "FROM auction ";
      $query .= "INNER JOIN ";
      $query .= "item ON auction.itemID = item.id ";
      $query .="LEFT JOIN ";
      $query .="itemimage on item.id = itemimage.itemID ";
      $query .= "LEFT JOIN ";
      $query .= "(SELECT MAX(bid.bidValue) AS currentBid,bid.auctionID FROM bid GROUP BY bid.auctionID) AS currentBid ";
      $query .= "ON currentBid.auctionID = auction.id ";
      $query .="INNER JOIN ";
      $query .="user ON user.id = auction.sellerID ";
      $query .= $where; 
      $query .= $query_order;
      $query .= $query_limit;
      

      $result = mysqli_query($connection,$query);
      if ($result){
        while ($row = mysqli_fetch_assoc($result)){
          $auctions[] = $row;
        }
      }else {
        die( "Database query failed (search auction 1). " . mysqli_error( $connection ) );
      } 

      //get total number of auctions
      $query2 = "SELECT COUNT(*) AS count FROM auction ";
      $query2 .= "INNER JOIN ";
      $query2 .= "item ON auction.itemID = item.id ";
      $query2 .= $where;

      $totalPages = 1;
      $result = mysqli_query($connection,$query2);
      if ($result){
        $count = mysqli_fetch_assoc($result);
        $totalPages = ceil($count["count"]/$perPage);
      }else {
        die( "Database query failed (search auction 2). " . mysqli_error( $connection ) );
      } 

      
    } else {
      //if there is no keywords, get new auctions
      $query_new = "SELECT ";
      $query_new .="auction.id, ";
      $query_new .="itemimage.imageURL, ";
      $query_new .="item.itemName, ";
      $query_new .="user.name, ";
      $query_new .="IfNull(currentBid.currentBid, auction.startingBid) AS currentBid, ";
      $query_new .="auction.endAt, ";
      $query_new .="auction.sellerID FROM auction ";
      $query_count = "SELECT COUNT(*) AS count FROM auction ";

      $query ="INNER JOIN ";
      $query .="item ON item.id = auction.itemID ";
      $query .="LEFT JOIN ";
      $query .="itemimage on item.id = itemimage.itemID ";
      $query .="INNER JOIN ";
      $query .="user ON user.id = auction.sellerID ";
      $query .= "LEFT JOIN ";
      $query .= "(SELECT MAX(bid.bidValue) AS currentBid,bid.auctionID FROM bid GROUP BY bid.auctionID) AS currentBid ";
      $query .= "ON currentBid.auctionID = auction.id ";
      $query .=$where;
      $query .=$query_order;
      $query .=$query_limit;


      $result = mysqli_query($connection,$query_new.$query);
      if ($result){
        while ($row = mysqli_fetch_assoc($result)){
          $auctions[] = $row;
        }
      }else {
        die( "Database query failed (search auction 2). " . mysqli_error( $connection ) );
      }

      //get total pages for new auctions
      $totalPages = 1;
      $result = mysqli_query($connection,$query_count.$query);
      if ($result){
        $count = mysqli_fetch_assoc($result);
        $totalPages = ceil($count["count"]/$perPage);
      }else {
        die( "Database query failed (search auction 2 total). " . mysqli_error( $connection ) );
      } 
      
    }

    

    return ["auctions"=>$auctions,"totalPages"=>$totalPages>0 ? $totalPages : 1];
  }

  public function getBuyingAuctions($connection,$userID){
    $query = "SELECT auction.*,bid.bidValue,bid.buyerID,item.itemName,item.description,";
    $query .= "itemimage.imageURL,item.ownerID,item.categoryID,currentBid.currentBid FROM auction ";
    $query .= "INNER JOIN ";
    $query .= "bid ON bid.auctionID = auction.ID ";
    $query .= "INNER JOIN ";
    $query .= "item ON auction.itemID = item.id ";
    $query .= "LEFT JOIN ";
    $query .= "itemimage on item.id = itemimage.itemID ";
    $query .= "LEFT JOIN ";
    $query .= "(SELECT MAX(bid.bidValue) AS currentBid,bid.auctionID FROM bid GROUP BY bid.auctionID) AS currentBid ";
    $query .= "ON currentBid.auctionID = auction.id ";
    $query .= "WHERE ";
    $query .= "auction.endAt >= NOW() and ";
    $query .= "bid.bidValue =( ";
    $query .= "SELECT MAX(bid.bidValue) FROM bid ";
    $query .= "WHERE bid.auctionID = auction.id ";
    $query .= "AND bid.buyerID = {$userID}) ";
    $query .= "group by bid.auctionID";
  
    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getBuyingAuctions). " . mysqli_error( $connection ) );
    }



    return $auctions;
  }

  public function getSellingAuctions($connection,$userID){
    $query = "SELECT auction.*,bid.bidValue,bid.buyerID,item.itemName,item.description,";
    $query .= "itemimage.imageURL,item.ownerID,item.categoryID,currentBid.currentBid FROM auction ";
    $query .= "LEFT JOIN ";
    $query .= "item ON auction.itemID = item.id ";
    $query .= "LEFT JOIN ";
    $query .= "bid ON bid.auctionID = auction.id ";
    $query .= "LEFT JOIN ";
    $query .= "itemimage on item.id = itemimage.itemID ";
    $query .= "LEFT JOIN ";
    $query .= "(SELECT MAX(bid.bidValue) AS currentBid,bid.auctionID FROM bid GROUP BY bid.auctionID) AS currentBid ";
    $query .= "ON currentBid.auctionID = auction.id ";
    $query .= "WHERE ";
    $query .= "sellerID = {$userID} and auction.endAt > NOW()";

    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getSellingAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

  public function getBoughtAuctions($connection,$userID){
    $query = "SELECT auction.*, winner.winnerID,winner.currentBid, user.name as sellerName, item.itemName ";
    $query .= "FROM auction ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query .= "FROM ( ";
    $query .= "SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query .= "FROM bid ";
    $query .= "GROUP BY bid.auctionID ";
    $query .= ") AS b1 ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query .= "FROM bid ";
    $query .= ") AS b2 ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query .= ") AS winner  ";
    $query .= "ON winner.auctionID=auction.id ";
    $query .= "LEFT JOIN ";
    $query .= "user ON auction.sellerID = user.id ";
    $query .= "LEFT JOIN ";
    $query .= "item ON auction.itemID = item.id ";
    $query .= "WHERE ";
    $query .= "winner.winnerID = {$userID} and auction.endAt <= NOW()";

    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $row["didFeedback"] = $this->didFeedback($connection,$userID,$row["id"]);
        $auctions[] = $row;

      }
    } else {
      die( "Database query failed (getBoughtingAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

  public function getSoldAuctions($connection,$userID){
    $userID = $this->e($connection, $userID);
    $query = "SELECT auction.*, winner.winnerID,winner.currentBid, user.name as winnerName, item.itemName ";
    $query .= "FROM auction ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query .= "FROM ( ";
    $query .= "SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query .= "FROM bid ";
    $query .= "GROUP BY bid.auctionID ";
    $query .= ") AS b1 ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query .= "FROM bid ";
    $query .= ") AS b2 ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query .= ") AS winner  ";
    $query .= "ON winner.auctionID=auction.id ";
    $query .= "LEFT JOIN ";
    $query .= "user ON winner.winnerID = user.id ";
    $query .= "LEFT JOIN ";
    $query .= "item ON auction.itemID = item.id ";
    $query .= "WHERE ";
    $query .= "auction.sellerID = {$userID} AND user.name IS NOT NULL AND auction.endAt <= NOW()";

    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $row["didFeedback"] = $this->didFeedback($connection,$userID,$row["id"]);
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getSoldAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }

    public function getUnsoldAuctions($connection,$userID){
    $userID = $this->e($connection, $userID);
    $query = "SELECT auction.*, winner.winnerID,winner.currentBid, user.name as winnerName, item.itemName ";
    $query .= "FROM auction ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query .= "FROM ( ";
    $query .= "SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query .= "FROM bid ";
    $query .= "GROUP BY bid.auctionID ";
    $query .= ") AS b1 ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query .= "FROM bid ";
    $query .= ") AS b2 ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query .= ") AS winner  ";
    $query .= "ON winner.auctionID=auction.id ";
    $query .= "LEFT JOIN ";
    $query .= "user ON winner.winnerID = user.id ";
    $query .= "LEFT JOIN ";
    $query .= "item ON auction.itemID = item.id ";
    $query .= "WHERE ";
    $query .= "auction.sellerID = {$userID} AND user.name IS NULL and auction.endAt <= NOW()";

    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $row["didFeedback"] = $this->didFeedback($connection,$userID,$row["id"]);
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getUnsoldAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }


/* get auctions said user is watching atm */

  public function getWatchingAuctions($connection,$userID){
    $userID = $this->e($connection, $userID);
    $query = "SELECT auction.*,winner.winnerID, winner.currentBid, item.itemName,item.description,";
    $query .= "itemimage.imageURL,item.ownerID,item.categoryID FROM watching ";
    $query .= "INNER JOIN auction ";
    $query .= "ON watching.auctionID = auction.id ";
    $query .= "INNER JOIN item ";
    $query .= "ON auction.itemID = item.id ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query .= "FROM ( ";
    $query .= "SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query .= "FROM bid ";
    $query .= "GROUP BY bid.auctionID ";
    $query .= ") AS b1 ";
    $query .= "LEFT JOIN ( ";
    $query .= "SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query .= "FROM bid ";
    $query .= ") AS b2 ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query .= ") AS winner  ";
    $query .= "ON winner.auctionID=auction.id ";
    $query .="LEFT JOIN ";
    $query .="itemimage on item.id = itemimage.itemID ";
    $query .= "WHERE watching.userID = {$userID} ";

    $result = mysqli_query($connection,$query);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getWatchingAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }
/*
public function addWatch($connection,$userID, $auctionID){
    $userID = $this->e($connection, $userID, $auctionID);
    $query = "INSERT INTO watching ";
    $query .="VALUES({$userID}, {$auctionID}) ";   

    $result = mysqli_query($connection,$query, $auctionID);

    $auctions = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (getWatchingAuctions). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }
*/

  public function isWatchingAuction($connection, $userID, $auctionID) {
    $userID = $this->e($connection, $userID);
    $auctionID = $this->e($connection, $auctionID);
    $query = "select * from watching where userID = '{$userID}' and auctionID = '{$auctionID}'";
    $result = mysqli_query($connection,$query);
    if (mysqli_fetch_assoc($result)) {
      return true;
    } else {
      return false;
    }
  }

 public function setWatchingAuction($connection, $userID, $auctionID) {
    $userID = $this->e($connection, $userID);
    $auctionID = $this->e($connection, $auctionID);
    $query = "select * from watching where userID = '{$userID}' and auctionID = '{$auctionID}'";
    $result = mysqli_query($connection,$query);
    if ($row = mysqli_fetch_assoc($result)) {
      $query = "delete from watching where userID = '{$userID}' and auctionID = '{$auctionID}'";
      mysqli_query($connection,$query);
      return false;
    } else {
      $query = "insert into watching values ('{$userID}', '{$auctionID}')";
      mysqli_query($connection,$query);
      return true;
    }
  }




  /*
   * get new auction for homepage, defined by the date of creation
   */
  public function getNewAuctions($connection,$page,$perPage){
    $auctions = [];
    //get offset
    $offset = ($page-1)*$perPage;

    //get new auctions, ordered by how close they are to the end;
    $query ="SELECT ";
    $query .="auction.id, ";
    $query .="itemimage.imageURL, ";
    $query .="item.itemName, ";
    $query .="user.name, ";
    $query .="auction.sellerID ";
    $query .="FROM ";
    $query .="auction ";
    $query .="INNER JOIN ";
    $query .="item ON item.id = auction.itemID ";
    $query .="INNER JOIN ";
    $query .="user ON user.id = auction.sellerID ";
    $query .="LEFT JOIN ";
    $query .="itemimage on item.id = itemimage.itemID ";
    $query .="WHERE ";
    $query .="auction.endAt>NOW() ";
    $query .="ORDER BY ";
    $query .="auction.createdAt DESC ";
    $query .="LIMIT {$offset},{$perPage} ";

    $result = mysqli_query($connection,$query);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    }else {
      die( "Database query failed (new auction). " . mysqli_error( $connection ) );
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
    $query .="itemimage.imageURL, ";
    $query .="item.itemName, ";
    $query .="user.name, ";
    $query .="auction.sellerID ";
    $query .="FROM ";
    $query .="auction ";
    $query .="INNER JOIN ";
    $query .="item ON item.id = auction.itemID ";
    $query .="INNER JOIN ";
    $query .="user ON user.id = auction.sellerID ";
    $query .="LEFT JOIN ";
    $query .="itemimage on item.id = itemimage.itemID ";
    $query .="WHERE ";
    $query .="auction.endAt>NOW() ";
    $query .="ORDER BY ";
    $query .="auction.endAt ASC ";
    $query .="LIMIT {$limit} ";

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

  public function getRandomAuctions($connection,$limit,$userID){
    $auctions = [];
    $query = "SELECT COUNT(*) as ct FROM auction";
    $result = mysqli_query($connection, $query);
    if ($result){
      //get count
      $countEntry = mysqli_fetch_assoc($result);
      $count = $countEntry["ct"];
      if ($count==0){
        return $auctions;
      }
      //get random auctions
      $rand_a = [];
      for ($i=0;$i<$limit;$i++){
        $rand_a[] = mt_rand(1,$count);
      }
      $rand_s = implode(",",$rand_a);

      $query = "SELECT ";
      $query .="auction.id, ";
      $query .="itemimage.imageURL, ";
      $query .="item.itemName ";
      $query .= "FROM ";
      $query .= "auction ";
      $query .= "INNER JOIN ";
      $query .= "item ON item.id = auction.itemID ";
      $query .="LEFT JOIN ";
      $query .="itemimage on item.id = itemimage.itemID ";
      $query .= "WHERE ";
      $query .= "auction.id IN({$rand_s}) and auction.endAt > NOW() AND auction.sellerID<>$userID; ";

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

  public function getRecommendedAuctions($connection,$userID=0){
    //select all distinct categories user has bidded，ordered by number of categories
    //if results are not adequate, it will further select hottest auctions
    //in an attempt to attract user to bid them
    //
    $MAX_TOTAL_RECOMMENDATIONS = 10;

    $auctions=[];
    //only get these when user has logged in
    if ($userID!=0){
      $query_select = "SELECT ";
      $query_select .="auction.id, ";
      $query_select .="itemimage.imageURL, ";
      $query_select .="item.itemName ";
      $query_select .= "FROM auction ";
      $query_select .= "INNER JOIN ";
      $query_select .= "item ON item.id = auction.itemID ";
      $query_select .="INNER JOIN ";
      $query_select .="itemimage on itemimage.itemID = auction.itemID ";

      //you might want to bid on the sorts of things
      //other people, who have also bid on the sorts of things you have previously
      //bid on, are currently bidding on
      $query ="WHERE ";
      $query .="auction.id IN( ";
      $query .="SELECT bid.auctionID ";
      $query .="FROM bid ";
      $query .="WHERE ";
      $query .="bid.buyerID IN( ";
      $query .="SELECT bid.buyerID ";
      $query .="FROM bid ";
      $query .="WHERE ";
      $query .="bid.buyerID <> {$userID} AND bid.auctionID IN( ";
      $query .="SELECT bid.auctionID ";
      $query .="FROM bid ";
      $query .="WHERE ";
      $query .="bid.buyerID = {$userID} ";
      $query .="GROUP BY ";
      $query .="bid.auctionID) ";
      $query .="GROUP BY ";
      $query .="bid.buyerID) ";
      $query .="GROUP BY ";
      $query .="bid.auctionID) AND ";
      $query .="auction.endAt > NOW() ";

      //you might want to bid on the sorts of things
      //that are in the same category as the things you are currently bidding on
      //ordered by how frequent you bid on a certain category of items
      
      $query2 = "INNER JOIN ";
      $query2 .= "( SELECT ";
      $query2 .= "item.categoryID AS CategoryID, ";
      $query2 .= "COUNT(item.id) AS Occurrence ";
      $query2 .= "FROM item, ";
      $query2 .= "( SELECT ";
      $query2 .= "auction.itemID AS ItemID ";
      $query2 .= "FROM ";
      $query2 .= "auction, ";
      $query2 .= "(SELECT DISTINCT ";
      $query2 .= "bid.auctionID AS AuctionID ";
      $query2 .= "FROM bid ";
      $query2 .= "WHERE ";
      $query2 .= "bid.buyerID = {$userID} ";
      $query2 .= ") AS ab ";
      $query2 .= "WHERE ";
      $query2 .= "auction.id = ab.AuctionID ";
      $query2 .= ") AS ai ";
      $query2 .= "WHERE ";
      $query2 .= "item.id = ai.ItemID ";
      $query2 .= "GROUP BY ";
      $query2 .= "item.categoryID ";
      $query2 .= ") AS item_category ON item.categoryID = item_category.CategoryID ";
      $query2 .= "WHERE ";
      $query2 .= "auction.endAt > NOW() ";
      $query2 .= "AND auction.sellerID<>{$userID} ";
      $query2 .= "ORDER BY ";
      $query2 .= "item_category.Occurrence DESC, ";
      $query2 .= "auction.id DESC ";

      $query_limit = "LIMIT {$MAX_TOTAL_RECOMMENDATIONS}";
      $result = mysqli_query( $connection, $query_select.$query.$query_limit );

      //$this->container->get('dump')->d($query_select.$query.$query_limit);
      //get first type reco
      if ( $result ) {
        while ($row = mysqli_fetch_assoc($result)){
          $auctions[] = $row;
        }
      } else {
        die( "Database query failed (get recommended auction 1). " . mysqli_error( $connection ) );
      }

      // echo "q1:<pre>";
      // var_dump($query_select.$query.$query_limit);
      // echo "\n";
      // var_dump($auctions);
      // echo "</pre><br>";
      
      //if first type did not return enough reco, get second type reco
      $shortage = $MAX_TOTAL_RECOMMENDATIONS-count($auctions);
      $query2_limit = "LIMIT {$shortage}";
      $result2 = mysqli_query( $connection, $query_select.$query2.$query2_limit );

      //get second type reco
      if ( $result2 ) {
        while ($row = mysqli_fetch_assoc($result2)){
          $auctions[] = $row;
        }
      } else {
        die( "Database query failed (get recommended auction 2). " . mysqli_error( $connection ) );
      }
      // echo "q2:<pre>";
      // var_dump($auctions);
      // echo "</pre><br>";
    }

    //if reco auctions are not adequate, get random auctions
    $reco_count = count($auctions);
    if ($reco_count<$MAX_TOTAL_RECOMMENDATIONS){
        $more = $MAX_TOTAL_RECOMMENDATIONS-$reco_count;
        $random = $this->getRandomAuctions($connection,$more,$userID);
        if (count($auctions)== 0){
          $auctions = $random;
        } else {
          $auctions = array_merge($auctions,$random);
        }
        
      }

    return $auctions;
  }

  public function getSimilarAuctions($connection,$auction){
    //get up to 10 auctions whose category is same as the auction user is viewing right now

    $categoryID = $auction->item->categoryID;
    $query = "SELECT auction.id,itemimage.imageURL,item.itemName FROM auction ";
    $query .= "INNER JOIN item ON item.id = auction.itemID ";
    $query .="LEFT JOIN ";
    $query .="itemimage on item.id = itemimage.itemID ";
    $query .= "WHERE item.categoryID={$categoryID} ORDER BY auction.createdAt DESC";
    $result = mysqli_query( $connection, $query );

    $auctions = array();
    //get reco auctions
    if ( $result ) {
      while ($row = mysqli_fetch_assoc($result)){
        $auctions[] = $row;
      }
    } else {
      die( "Database query failed (get similar auction). " . mysqli_error( $connection ) );
    }

    return $auctions;
  }


  public function addAuctionViewCount($connection,$auctionID){
    $query = "UPDATE auction SET viewCount = viewCount + 1 WHERE auction.id = {$auctionID} ";
    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      return true;
    } else {
      die( "Database query failed (addAuctionViewCount). " . mysqli_error( $connection ) );
      return false;
    }
  }

  // Bid

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

  // public function updateBid( $connection, $bid ) {
  //   $bidValue = $bid->bidValue;
  //   $buyerID = $bid->buyerID;
  //   $auctionID = $bid->auctionID;
  //   $id=$bid->id;

  //   $query = "UPDATE bid SET ".
  //   $query .= "bidValue = {$bidValue},";
  //   $query .= "buyerID={$buyerID}, ";
  //   $query .= "auctionID={$auctionID} ";
  //   $query .= "WHERE id={$id} ";

  //   $result = mysqli_query( $connection, $query );
  //   $affected = mysqli_affected_rows( $connection );
  //   if ( $result && $affected >= 0 ) {
  //     return true;
  //   } else {
  //     die( "Database query failed (Bid Update). " . mysqli_error( $connection ) );
  //     return FALSE;
  //   }
  // }

  public function bid($connection, $bid, $auction ) {
    //check if auction is on,
    $now = date( "Y-m-dTH:i:s" );
    if ( $auction->endAt>$now ) {
      //if true, this bid has 3 cases: Highest new bid, Bidded by highest bidder but lower than highest bid, bidded by other but lower than highest bid
      $auctionID = $auction->id;
      $query = "SELECT * FROM bid WHERE ";
      $query .= "auctionID={$auctionID} ";
      $query .= "ORDER BY bidValue DESC,createdAt ASC ";
      $query .= "LIMIT 1";
      $result = mysqli_query( $connection, $query );
      if ($result){
        //get result
        $highestBid = mysqli_fetch_assoc( $result );
        if ( is_null( $highestBid ) ) {
          //never bidded yet
          if ($bid->bidValue>=$auction->startingBid){
            //save the bid and change current bid to the min value and winnerId to userId and return true and congrats
            if ( $bid->id = $this->addBid( $connection, $bid ) ) {
              return array( "status"=>"success", "message"=>"Congratulations" );
            } else {
              return array( "status"=>"danger", "message"=>"Unable to add bid" );
            }
          } else {
            return array( "status"=>"warning", "message"=>"Bid value is lower than starting bid" );
          }
        } else {
          //check if the bid is higher than highest bid plus min increase
          if ( $bid->bidValue >= $highestBid["bidValue"]+$auction->minBidIncrease ) {
            if ( $bid->id=$this->addBid( $connection, $bid ) ) {
              if ($bid->buyerID != $highestBid["buyerID"]){
                //send old highest bidder an email
                return array( "status"=>"success", "message"=>"Congratulations","second_buyerID"=>$highestBid["buyerID"]);
              } else {
                //update your bid
                return array( "status"=>"success", "message"=>"You successfully updated your bid." );
              }
            } else {
              //return server error
              return array( "status"=>"danger", "message"=>"Unable to save records" );
            }
          } else if ($bid->bidValue <= $highestBid["bidValue"]) {
            //price is lower than bid value
             if ($bid->buyerID == $highestBid["buyerID"]){
              //if bid is from the highest bidder, but its value less than highest bid value. Alert price lower than his previous amount
              return array( "status"=>"warning", "message"=>"You are the current highest bidder. You cannot place a value lower than or equal to your current bid." );
            } else {
              return array( "status"=>"warning", "message"=>"Price too low" );
            }
          } else {
            //price is lower than h bid + min inc but higher than h bid
            return array( "status"=>"warning", "message"=>"The minimum bid increase is ".$auction->minBidIncrease);
          }
        }
      } else {
        die("can't get highest bid");
      }
    } else {
      //return false and report auction is over.
      return array( "status"=>"warning", "message"=>"Auction is over" );
    }
  }

  public function getAllBids($connection,$auctionID){
    $query = "SELECT bid.*, user.name AS buyerName FROM bid ";
    $query .= "INNER JOIN user ON user.id = bid.buyerID ";
    $query .= "WHERE bid.auctionID={$auctionID} ORDER BY bid.createdAt DESC";
    $result = mysqli_query($connection,$query);
    $bids = [];
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $bids[] = $row;
      }
    } else {
      die( "Database query failed (getAllBids). " . mysqli_error( $connection ) );
      return false;
    }

    return $bids;
  }

  public function bidded( $connection, $auctionID, $userID ) {
    $query = "SELECT COUNT(*) as totalno FROM bid ";
    $query .= "WHERE auctionID={$auctionID} AND buyerID={$userID}";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $count = mysqli_fetch_assoc( $result );
      if ( $count["totalno"]>0 ) {
        return true;
      } else {
        return false;
      }
    }else {
      return false;
    }

  }

  // User

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
    $id = $this->e($connection,$user->id); 
    $name = $this->e($connection,$user->name);
    $email = $this->e($connection,$user->email);
    $password = $this->e($connection,$user->password);
    $newPassword = $this->e($connection,$user->newPassword);
    // reset password
    if ( !is_null( $email ) && $this->login( $user ) ) {
      if ( !is_null( $newPassword ) ) {
        $newPassword = $this->encrpt( $newPassword );
        $query = "UPDATE user SET ";
        $query .= "password='{$newPassword}' ";
        $query .= "WHERE id = {$id}";
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
    $nameOrEmail = $this->e( $connection, $user->nameOrEmail );
    $query = "SELECT * FROM user WHERE email='{$nameOrEmail}' OR name='{$nameOrEmail}' LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $fetched_user = mysqli_fetch_assoc( $result );
      if ( $user->password == $fetched_user["password"] || $this->check_password( $user->password, $fetched_user["password"] ) ) {
        return $fetched_user["id"];
      } else {
        return false;
      }
    } else {
      die( "Database query failed (User login). " . mysqli_error( $connection ) );
      return false;
    }
  }

  // feedback
  
  public function getFeedbacks($connection,$userID){
    $userID = $this->e($connection,$userID);

    //get sold and giverName
    $query_sold = "SELECT feedback.*,user.name AS giverName FROM feedback ";
    $query_sold .=" LEFT JOIN ( ";
    $query_sold .=" SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query_sold .=" FROM ( ";
    $query_sold .=" SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query_sold .=" FROM bid ";
    $query_sold .=" GROUP BY bid.auctionID ";
    $query_sold .=" ) AS b1 ";
    $query_sold .=" LEFT JOIN ( ";
    $query_sold .=" SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query_sold .=" FROM bid ";
    $query_sold .=" ) AS b2 ";
    $query_sold .=" ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query_sold .=" ) AS winner ON winner.auctionID = feedback.auctionID ";
    $query_sold .= "INNER JOIN user ON user.id = winner.winnerID ";
    $query_sold .= "INNER JOIN auction ON auction.id = feedback.auctionID ";
    $query_sold .= "WHERE auction.sellerID = {$userID} ";
    $query_sold .= "AND feedback.giverID = winner.winnerID";
    $feedbacks = [];
    $result = mysqli_query($connection,$query_sold);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $feedbacks[] = $row;
      }
    }else {
      die( "Database query failed (get feedbacks 1). " . mysqli_error( $connection ) );
    }



    //get bought 
    $query_bought =" SELECT feedback.*,user.name AS giverName FROM feedback ";
    $query_bought .= "INNER JOIN user ON user.id = feedback.giverID ";
    $query_bought .=" LEFT JOIN ( ";
    $query_bought .=" SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query_bought .=" FROM ( ";
    $query_bought .=" SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query_bought .=" FROM bid ";
    $query_bought .=" GROUP BY bid.auctionID ";
    $query_bought .=" ) AS b1 ";
    $query_bought .=" LEFT JOIN ( ";
    $query_bought .=" SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query_bought .=" FROM bid ";
    $query_bought .=" ) AS b2 ";
    $query_bought .=" ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query_bought .=" ) AS winner ";
    $query_bought .=" ON winner.auctionID = feedback.auctionID WHERE winner.winnerID={$userID} ";
    $query_bought .=" AND feedback.giverID <> {$userID}";


    $result2 = mysqli_query($connection,$query_bought);
    if ($result2){
      while ($row2 = mysqli_fetch_assoc($result2)){
        $feedbacks[] = $row2;
      }
    }else {
      die( "Database query failed (get feedbacks 2). " . mysqli_error( $connection ));
    }

    return $feedbacks;
  }

  /*
   * This function check if a giver can give a receiver feedback for an particular auction
   * It is based on if user's eligibility and if user has already given feedback
   */
  public function shouldFeedback($connection,$giverID,$auctionID){
    $giverID = $this->e($connection,$giverID);
    $auctionID = $this->e($connection, $auctionID);

    $query ="SELECT COUNT(*) AS ct  ";
    $query .="FROM auction, ";
    $query .= "(SELECT b2.auctionID,b2.winnerID,b1.currentBid ";
    $query .= "FROM (SELECT MAX(bid.bidValue) AS currentBid ";
    $query .= "FROM bid ";
    $query .= "WHERE bid.auctionID = {$auctionID}) AS b1 ";
    $query .= "INNER JOIN (SELECT bid.auctionID, bid.buyerID AS winnerID, bid.bidValue ";
    $query .= "FROM bid ) AS b2 ON b2.auctionID = {$auctionID} AND b2.bidValue = b1.currentBid) AS winner ";
    $query .="WHERE ";
    $query .="auction.id={$auctionID} ";
    $query .="AND auction.endAt <= NOW() ";
    $query .="AND auction.sellerID={$giverID} OR winner.winnerID=($giverID) ";
    $result = mysqli_query($connection,$query);
    if ($result){
      $count = mysqli_fetch_assoc($result);
      return ($count["ct"]>1 && ! $this->didFeedback($connection,$giverID,$auctionID));
    } else {
      die( "Database query failed (shouldFeedback). " . mysqli_error( $connection ) );
      return false;
    }

  } 

  public function didFeedback($connection,$giverID,$auctionID){
    $query ="SELECT COUNT(*) as ct ";
    $query .="FROM feedback ";
    $query .="WHERE ";
    $query .="feedback.giverID = {$giverID} ";
    $query .="AND feedback.auctionID = {$auctionID}";
    $result = mysqli_query($connection,$query);
    if ($result){
      $didFeedback = mysqli_fetch_assoc($result);
      if ($didFeedback["ct"]==0){
        return false;
      } else {
        return true;
      }
    } else {
      die( "Database query failed (didFeedback). " . mysqli_error( $connection ) );
      return false;
    }
  }

  public function selectFeedback($connection,$giverID,$auctionID){
    $giverID = $this->e($connection,$giverID);
    $auctionID = $this->e($connection, $auctionID);

    $query = "SELECT * FROM feedback WHERE giverID={$giverID} AND auctionID={$auctionID} LIMIT 1";
    $result = mysqli_query( $connection, $query );
    if ( $result ) {
      $object = mysqli_fetch_assoc( $result );
      return $object;
    } else {
      return false;
    }

  }
  
  /*
   * @return ["status" : can be success, warning, danger, info, 
   *          "reason": reason of failure, 
   *          "id": the generated id of a new feedback]
   */
  public function feedback($connection,$feedback){
    $giverID= $this->e($connection,$feedback->giverID);
    $auctionID= $this->e($connection,$feedback->auctionID);
    $rating= $this->e($connection,$feedback->rating);
    $comment= $this->e($connection,$feedback->comment);
    //double check if one can leave feedback
    $shouldFeedback = $this->shouldFeedback($connection,$giverID,$auctionID);
    if ($shouldFeedback){
      $query = "INSERT INTO feedback (giverID,rating,comment,auctionID) ";
      $query .= "VALUES ({$giverID},{$rating},'{$comment}',{$auctionID})";
      $result = mysqli_query( $connection, $query );
      if ( $result ) {
        $id =  mysqli_insert_id( $connection );
        return array("status"=>"success","feedback_id"=>$id);
      } else {
        die( "Database query failed (feedback). " . mysqli_error( $connection ) );
        return false;
      }
      return false;
    } else {
      //TODO: next version, it will check why feedback failed
      return array("status"=>"warning","reason"=>"You can't leave feedback for this auction.");
    }
  }

  public function updateFeedback($connection,$feedback){
    $giverID=$this->e($connection,$feedback->giverID);
    $auctionID=$this->e($connection,$feedback->auctionID);
    $rating=$this->e($connection,$feedback->rating);
    $comment=$this->e($connection,$feedback->comment);
    $query = "UPDATE feedback SET ";
    $query .= "rating={$rating},comment='{$comment}' ";
    $query .= "WHERE giverID={$giverID} AND auctionID={$auctionID}";

    $result = mysqli_query( $connection, $query );
    $affected = mysqli_affected_rows( $connection );
    if ( $result && $affected >= 0 ) {
      return true;
    } else {
      die( "Database query failed (Feedback Update). " . mysqli_error( $connection ) );
      return false;
    }
  }

  /*
   * Get ratings from all feedbacks received for particular user and return the average
   * @return: average rating for this user and total number of feedbacks received.
   */
  public function getAverageRating($connection,$userID){
    $userID = $this->e($connection,$userID);
    
    $ratings = [];
    //get sold and giverName
    $query_sold = "SELECT feedback.rating FROM feedback ";
    $query_sold .=" LEFT JOIN ( ";
    $query_sold .=" SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query_sold .=" FROM ( ";
    $query_sold .=" SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query_sold .=" FROM bid ";
    $query_sold .=" GROUP BY bid.auctionID ";
    $query_sold .=" ) AS b1 ";
    $query_sold .=" LEFT JOIN ( ";
    $query_sold .=" SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query_sold .=" FROM bid ";
    $query_sold .=" ) AS b2 ";
    $query_sold .=" ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query_sold .=" ) AS winner ON winner.auctionID = feedback.auctionID ";
    $query_sold .= "INNER JOIN user ON user.id = winner.winnerID ";
    $query_sold .= "INNER JOIN auction ON auction.id = feedback.auctionID ";
    $query_sold .= "WHERE auction.sellerID = {$userID} ";
    $query_sold .= "AND feedback.giverID = winner.winnerID";

    $result = mysqli_query($connection,$query_sold);
    if ($result){
      while ($row = mysqli_fetch_assoc($result)){
        $ratings[] = $row["rating"];
      }
    }else {
      die( "Database query failed (get feedbacks 1). " . mysqli_error( $connection ) );
    }



    //get bought 
    $query_bought =" SELECT feedback.rating FROM feedback ";
    $query_bought .= "INNER JOIN user ON user.id = feedback.giverID ";
    $query_bought .=" LEFT JOIN ( ";
    $query_bought .=" SELECT b1.auctionID,b1.highestBid as currentBid,b2.buyerID as winnerID ";
    $query_bought .=" FROM ( ";
    $query_bought .=" SELECT bid.auctionID,MAX(bid.bidValue) AS highestBid ";
    $query_bought .=" FROM bid ";
    $query_bought .=" GROUP BY bid.auctionID ";
    $query_bought .=" ) AS b1 ";
    $query_bought .=" LEFT JOIN ( ";
    $query_bought .=" SELECT bid.auctionID, bid.bidValue, bid.buyerID ";
    $query_bought .=" FROM bid ";
    $query_bought .=" ) AS b2 ";
    $query_bought .=" ON b2.auctionID = b1.auctionID AND b2.bidValue = b1.highestBid ";
    $query_bought .=" ) AS winner ";
    $query_bought .=" ON winner.auctionID = feedback.auctionID WHERE winner.winnerID={$userID} ";
    $query_bought .=" AND feedback.giverID <> {$userID}";


    $result2 = mysqli_query($connection,$query_bought);
    if ($result2){
      while ($row2 = mysqli_fetch_assoc($result2)){
        $ratings[] = $row2["rating"];
      }
    }else {
      die( "Database query failed (get feedbacks 2). " . mysqli_error( $connection ));
    }

    $avg = 0;
    if (count($ratings)>0){
     $avg = array_sum($ratings) / count($ratings); 
    }

    return $avg;
    
  }

  // category

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

  // misc

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

  private function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }
}

?>
