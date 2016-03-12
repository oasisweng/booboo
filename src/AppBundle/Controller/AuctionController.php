<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;


use AppBundle\Entity\Auction;
use AppBundle\Entity\Item;
use AppBundle\Form\Type\AuctionType;
use AppBundle\Entity\Bid;
use AppBundle\Entity\User;
use AppBundle\Entity\Filter;
use AppBundle\Form\Type\BidType;
use AppBundle\Form\Type\FilterType;


class AuctionController extends Controller {

    /**
     *  get search result based on keywords and page number
     *
     * @Route("/search/{page}", name = "auction_search",
     *                            requirements = {"page": "\d+"},
     *                            defaults = {"page" : 1})
     */
    public function searchAction( $page = 1, Request $request ) {
        //page cannot be zero
        if ( $page == 0 ) {
            $this->addFlash(
                'warning',
                'Page number cannot be zero!'
            );

            return $this->redirectToRoute( 'homepage' );
        }
        //get data
        //remove leading and trailing whitespace for keywords
        $keywords_s = ltrim( rtrim( $request->get( 'keywords' ) ) );
        $keywords_a = [];
        if ( strlen( $keywords_s )>0 ) {
            $keywords_a = explode( ' ', $keywords_s );
        }

        $connection = $this->get( 'db' )->connect();

        //get filters
        // 1) build the form
        $filter = new Filter();
        $filter->categories = NULL;

        $filter_form = $this->createForm( FilterType::class, $filter );

        // 2) handle the submit (will only happen on POST)
        $filter_form->handleRequest( $request );
        if ( $filter_form->isSubmitted() && $filter_form->isValid() ) {
            // ... do any other work - like send them an email, etc
            // maybe set a "flash" success message for the user
            $this->addFlash(
                'success',
                'Result is filtered!'
            );
            // $this->get('dump')->d($filter);
        }

        //pass in filter
        $searchResults = $this->get( 'db' )->searchAuctions( $connection, $keywords_a, $page, 25,  $filter );

        
        // $this->get('dump')->d($searchResults);
        if ( $request->isXmlHttpRequest() ) {
            //return json
            return new JsonResponse( ['result'=>$searchResults, 'page'=>$page] );
        } else {
            //return page
            $totalPages = $searchResults["totalPages"];
            if ( $page>$totalPages ) {
                //page number is larger than total pages of data
                $this->addFlash(
                    'warning',
                    'Page number exceeds total number of pages!'
                );

                return $this->redirectToRoute( 'homepage' );
            }

            return $this->render( 'auction/search.html.twig', array(
                    'totalPages' => $totalPages,
                    'auctions' => $searchResults["auctions"],
                    'page' => $page,
                    'filter_form' => $filter_form->createView()
                ) );
        }
    }

    /**
     *
     *
     * @Route("auction/new", name="auction_new")
     */
    public function newAction( Request $request ) {
        //get user and check if user has logged in 
        $session = $request->getSession();   
        $userID = $session->get('userID');
        if ( !isset( $userID ) ) {
            //return to login page
            $this->addFlash(
                'warning',
                'You need to login first!'
            );
            return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ) ), 301 );
        } 

        $auction = new Auction();
        $auction->sellerID = $userID;
        $form = $this->createForm( AuctionType::class, $auction );


        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... perform some action, such as saving the task to the database
            // 
            
            $connection = $this->get( 'db' )->connect();
            if ( $auctionID = $this->get( 'db' )->addAuction( $connection, $auction ) ) {
                $this->addFlash(
                    'success',
                    'New Auction created!'
                );

                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            } else {
                $this->addFlash(
                    'danger',
                    'Auction failed to create!'
                );
            }

        }

        return $this->render( 'auction/new.html.twig', array(
                'form' => $form->createView(),
            ) );
    }

     /**
     *
     *
     * @Route("/auction/watch/{auctionID}/{userID}", name="auction_watch", requirements={"auctionID": "\d+", "userID": "\d+"})
     */
    public function watchAction( $auctionID, $userID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        $result = $this->get('db')->setWatchingAuction($connection, $userID, $auctionID);
        return new JsonResponse($result);
    }

    /**
     *
     *
     * @Route("/auction/{auctionID}", name="auction_show", requirements={"auctionID": "\d+"})
     */
    public function showAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        // add view count
        $this->get('db')->addAuctionViewCount($connection,$auctionID);

        //get data
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );
        $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auction->itemID );
        $item = new Item( $itemEntry );
        $auction->item = $item;
        $category = $this->get( "db" )->selectOne( $connection, 'category', $auction->item->categoryID );
        $item->categoryName = $category["categoryName"];

        $sellerEntry = $this->get( "db" )->selectOne( $connection, 'user', $auction->sellerID );
        $seller = new User($sellerEntry);
        
        //if should finish auction through this way
        if ( $this->get( 'db' )->shouldFinishAuction( $auction ) ) {
            $response = $this->get( 'db' )->finishAuction( $connection, $auction );
            //CASE 1: seller sold the item, one user won
            if ($response['status']=='success'){
                //send seller and winner congrad emails
                $sellerEntry = $this->get( 'db' )->selectOne( $connection, "user", $auction->sellerID);
                $name = $sellerEntry["name"];
                $email = $sellerEntry["email"];
                try{
                //send email
                $message = \Swift_Message::newInstance()
                ->setSubject( 'You sold '.$auction->item->itemName.'!' )
                ->setFrom( 'boobooauction@gmail.com')
                ->setTo( $email )
                ->setBody(
                    $this->renderView(
                        'Emails/sold.html.twig',
                        array( 'name' => $name,
                            'auctionID'=>$auctionID,
                            'itemName'=>$auction->item->itemName )
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );
                }
                catch (Swift_TransportException $STe) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
                    echo $string;
                    // send error note to user
                    echo "the mail service has encountered a problem. Please retry later or contact the site admin.";
                }
                catch (Exception $e) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - GENERAL ERROR - ' . $e->getMessage() . PHP_EOL;
                    echo $string;
                    // redirect to error page
                    $app->abort(500, "Oops, something went seriously wrong. Please retry later !");
                }

                $winnerEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["winnerID"]);
                $item->ownerID = $response["winnerID"];
                $this->get('db')->updateItem($connection,$item);

                $wname = $winnerEntry["name"];
                $wemail = $winnerEntry["email"];
                try {
                    //send email to winner
                    $message = \Swift_Message::newInstance()
                    ->setSubject( 'You bought '.$auction->item->itemName.'!' )
                    ->setFrom( 'boobooauction@gmail.com' )
                    ->setTo( $wemail )
                    ->setBody(
                        $this->renderView(
                            'Emails/won.html.twig',
                            array( 'name' => $wname,
                                'auctionID'=>$auctionID,
                                'itemName'=>$auction->item->itemName )
                        ),
                        'text/html'
                    );
                }
                catch (Swift_TransportException $STe) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
                    echo $string;
                    // send error note to user
                    echo "the mail service has encountered a problem. Please retry later or contact the site admin.";
                }
                catch (Exception $e) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - GENERAL ERROR - ' . $e->getMessage() . PHP_EOL;
                    echo $string;
                    // redirect to error page
                    $app->abort(500, "Oops, something went seriously wrong. Please retry later !");
                }

                $this->get( 'mailer' )->send( $message );
                //var_dump($winnerEntry);

            } else if ($response['status']=="warning"){
                //CASE 2: seller did not sell the item because no one bidded
                //CASE 3: the highest bid didnt meet the reserved price
                //send seller unsold email
                $sellerEntry = $this->get( 'db' )->selectOne( $connection, "user", $auction->sellerID);
                $name = $sellerEntry["name"];
                $email = $sellerEntry["email"];
                $reason = "";
                if ($response['message']=="reserved price unmet"){
                    $reason = "The highest bid did not meet the reserved price you have set";
                } else if ($response['message']=="no bid"){
                    $reason = "No one bidded your auction";
                }
                //send email
                $message = \Swift_Message::newInstance()
                ->setSubject( 'You'.$auction->item->itemName.' was not sold!' )
                ->setFrom( 'boobooauction@gmail.com' )
                ->setTo( $email )
                ->setBody(
                    $this->renderView(
                        'Emails/unsold.html.twig',
                        array( 'name' => $name,
                            'auctionID'=>$auctionID,
                            'itemName'=>$auction->item->itemName,
                            'reason'=>$reason )
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );
                if ($response['message']=="reserved price unmet"){
                    //CASE 3:
                    //send winner rpnm emails
                    $winnerEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["winnerID"]);
                    $wname = $winnerEntry["name"];
                    $wemail = $winnerEntry["email"];
                    //send email
                    $message = \Swift_Message::newInstance()
                    ->setSubject( 'You did not meet reserved price for '.$auction->item->itemName.'!' )
                    ->setFrom( 'boobooauction@gmail.com' )
                    ->setTo( $wemail )
                    ->setBody(
                        $this->renderView(
                            'Emails/reservedPriceNotMeet.html.twig',
                            array( 'name' => $wname,
                                'auctionID'=>$auctionID,
                                'itemName'=>$auction->item->itemName )
                        ),
                        'text/html'
                    );
                    $this->get( 'mailer' )->send( $message );
                }
            } 
            $auction->ended = true;
        }

        //get userID, or null if not logged in
        $session = $request->getSession();   
        $userID = $session->get('userID');

        //get similar auctions
        //get imageURL using similarAuctions[i].imageURL and 
        //name using similarAuctions[i].itemName and id using similarAuctions[i].id
        $similarAuctions = $this->get('db')->getSimilarAuctions($connection,$auction);

        //bid form
        $bid = new Bid();
        $bid->auctionID = $auctionID;
        $bidForm = $this->createForm( BidType::class, $bid );

        //handle bid
        $bidForm->handleRequest( $request );

        if ( $bidForm->isSubmitted() && $bidForm->isValid() ) {

            if ( !isset( $userID ) ) {
                //return to login page
                $this->addFlash(
                    'warning',
                    'You need to login first!'
                );
                return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ), "params"=>['auctionID'=>$auctionID] ), 301 );
            } else if ( $userID == $auction->sellerID ) {
                //return to auction page
                $this->addFlash(
                    'warning',
                    'You can\'t bid on your auction.' );
            } else {

                //set userID
                $bid->buyerID = $userID;

                //attempt to bid
                $response = $this->get( 'db' )->bid( $connection, $bid, $auction );
                //$this->get( 'dump' )->d( $response );
                if ( $response["status"] == "success" ) {
                    $this->addFlash(
                        'success',
                        $response["message"]
                    );
                    $auction->currentBid = $bid->bidValue;
                    //get the user who has been outbid, send an email
                    if ( array_key_exists( "second_buyerID", $response ) ) {
                        //if someone has been outbid and its not current buyer, get user name
                        $userEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["second_buyerID"] );
                        $name = $userEntry["name"];
                        $email = $userEntry["email"];
                        //send email
                        $message = \Swift_Message::newInstance()
                        ->setSubject( 'You are outbid!' )
                        ->setFrom( 'boobooauction@gmail.com' )
                        ->setTo( $email )
                        ->setBody(
                            $this->renderView(
                                'Emails/outbid.html.twig',
                                array( 'name' => $name,
                                    'auctionID'=>$auctionID )
                            ),
                            'text/html'
                        );
                        $this->get( 'mailer' )->send( $message );
                    }

                } else {
                    $this->addFlash(
                        'warning',
                        $response["message"]
                    );
                }
            }

            //return $this->redirectToRoute( 'auction_show',array('auctionID'=>$auctionID), 301);

        }

                //get all bids for this auction
        $bidEntries = $this->get('db')->getAllBids($connection,$auctionID);
        //$this->get('dump')->d($bidEntries);
        $bids = [];
        foreach ($bidEntries as $bidEntry){
            if ($bidEntry){
                $bids[] = new Bid($bidEntry);    
            }
        }

        $ended = $auction->ended;
        $bidded = isset( $userID ) && $this->get( 'db' )->bidded( $connection, $auctionID, $userID );
        //echo $auctionID . " ". $userID . " "; var_dump($bidded);
        $winning = $bidded && $this->get( 'db' )->getWinnerForAuction($connection,$auctionID)==$userID;
        $won = $ended && $winning;

        // var_dump($ended);
        // var_dump($bidded);
        // var_dump($winning);

        //check if auction is alr being watched by user
        $watching = $this->get('db')->isWatchingAuction($connection, $userID, $auctionID);
        //prepare return objects
        $params = array( "auction"=>$auction,
            "ended"=>$ended,
            "bidded"=>$bidded,
            "won"=>$won,
            "winning"=>$winning,
            "bid_form" => $bidForm->createView(),
            "bids" => $bids,
            "seller" => $seller,
            "similarAuctions" => $similarAuctions,
            "watching" => $watching );

        return $this->render( 'auction/show.html.twig',
            $params );
    }

    /**
     *
     *
     * @Route("/auction/{auctionID}/edit", name="auction_edit", requirements={"auctionID": "\d+"})
     */
    public function editAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        if ( $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID ) ) {
            //check if user has right to edit
            $session = $request->getSession();   
            $userID = $session->get('userID');
            if ($userID != $auctionEntry["sellerID"]){
                $this->addFlash(
                    'warning',
                    'You don\'t have rights to edit this auction!'
                );
                return $this->redirectToRoute( 'homepage' );
            }

            // $this->get( 'dump' )->d( $auctionEntry );
            $auction = new Auction( $auctionEntry );

            if ($auction->endAt->format('Y-m-d H:i:s') <date("Y-m-d H:i:s")) {
                // auction has ended
                $this->addFlash(
                    'warning',
                    'This auction has ended'
                );

                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            }
            $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auctionEntry["itemID"] );
            $auction->item = new Item( $itemEntry );

            $form = $this->createForm( AuctionType::class, $auction );;

            $form->handleRequest( $request );

            if ( $form->isSubmitted() && $form->isValid() ) {
                if ( $this->get( 'db' )->updateAuction( $connection, $auction ) ) {

                    $this->addFlash(
                        'success',
                        'Auction "'.$auction->item->itemName.'" updated!'
                    );

                    return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
                } else {
                    $this->addFlash(
                        'warning',
                        'Something went wrong in updating this auction!'
                    );
                }
            }

            return $this->render( 'auction/edit.html.twig', array(
                    'form' => $form->createView(),
                    'auction' => $auction
                ) );
        } else {
            $this->addFlash(
                'warning',
                'The auction selected does not exist!'
            );

            return $this->redirectToRoute( 'homepage' );
        }
    }

    /**
     *
     *
     * @Route("/auctions/updateSeller", name="auction_update_seller")
     */
    public function updateSellerAction(Request $request){

        //connect to db
        $connection = $this->get('db')->connect();

        $auctions = $this->get('db')->countNewBids($connection);

        //for each auction, send an email
        foreach ($auctions as $auction){
            $name = $auction["name"];
            $email = $auction["email"];
            //send email
            $message = \Swift_Message::newInstance()
            ->setSubject( 'You have '.$auction["ct"].' new bids on '.$auction["itemName"].'!' )
            ->setFrom( 'boobooauction@gmail.com')
            ->setTo( $email )
            ->setBody(
                $this->renderView(
                    'Emails/updatebid.html.twig',
                    array( 'name' => $name,
                        'auctionID'=>$auction["auctionID"],
                        'itemName'=>$auction["itemName"],
                        'bidCount'=>$auction["ct"],
                        'updatedTo'=>$auction["updatedTo"] )
                ),
                'text/html'
            );
            $this->get( 'mailer' )->send( $message );
        }


        return $this->render( 'auction/updateSeller.html.twig', array() );

    }

    /**
     *
     *
     * @Route("/category/{categoryName}", name="auction_category")
     */
    public function categoryAction($categoryName,Request $request){
        //connect to db
        $connection = $this->get('db')->connect();

        $auctions = $this->get('db')->getAuctionsWithCategoryName($connection,$categoryName);

        // $this->get('dump')->d($categoryName);
        // $this->get('dump')->d($auctions);

        return $this->render( 'auction/category.html.twig', array(
            'auctions' => $auctions,
            'categoryName'=> $categoryName) );
    }


    /**
     *
     *
     * @Route("/auction/{auctionID}/columns/{columns}", name="auction_columns_api", requirements={"auctionID": "\d+", "columns": ".+"})
     */
    public function getAuctionColumns($auctionID, $columns){
        $columns_a = explode("/",$columns);
    
        $connection = $this->get('db')->connect();

        $auction = $this->get('db')->selectAuctionColumns($connection,$auctionID, $columns_a);

        return new JsonResponse( ['auction'=>$auction] );
    }

    /**
     *
     *
     * @Route("/auction/{auctionID}/finish", name="auction_finish_api", requirements={"auctionID": "\d+"})
     */
    public function finishAuction($auctionID){
        $connection = $this->get('db')->connect();
        $auctionEntry = $this->get('db')->selectOne($connection,'auction',$auctionID);
        $auction = new Auction($auctionEntry);

         //if should finish auction through this way
        if ( $this->get( 'db' )->shouldFinishAuction( $auction ) ) {
            $response = $this->get( 'db' )->finishAuction( $connection, $auction );
            //CASE 1: seller sold the item, one user won
            if ($response['status']=='success'){
                //send seller and winner congrad emails
                $sellerEntry = $this->get( 'db' )->selectOne( $connection, "user", $auction->sellerID);
                $name = $sellerEntry["name"];
                $email = $sellerEntry["email"];
                try{
                //send email
                $message = \Swift_Message::newInstance()
                ->setSubject( 'You sold '.$auction->item->itemName.'!' )
                ->setFrom( 'boobooauction@gmail.com')
                ->setTo( $email )
                ->setBody(
                    $this->renderView(
                        'Emails/sold.html.twig',
                        array( 'name' => $name,
                            'auctionID'=>$auctionID,
                            'itemName'=>$auction->item->itemName )
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );
                }
                catch (Swift_TransportException $STe) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
                    echo $string;
                    // send error note to user
                    echo "the mail service has encountered a problem. Please retry later or contact the site admin.";
                }
                catch (Exception $e) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - GENERAL ERROR - ' . $e->getMessage() . PHP_EOL;
                    echo $string;
                    // redirect to error page
                    $app->abort(500, "Oops, something went seriously wrong. Please retry later !");
                }

                $winnerEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["winnerID"]);
                $item->ownerID = $response["winnerID"];
                $this->get('db')->updateItem($connection,$item);

                $wname = $winnerEntry["name"];
                $wemail = $winnerEntry["email"];
                try {
                    //send email to winner
                    $message = \Swift_Message::newInstance()
                    ->setSubject( 'You bought '.$auction->item->itemName.'!' )
                    ->setFrom( 'boobooauction@gmail.com' )
                    ->setTo( $wemail )
                    ->setBody(
                        $this->renderView(
                            'Emails/won.html.twig',
                            array( 'name' => $wname,
                                'auctionID'=>$auctionID,
                                'itemName'=>$auction->item->itemName )
                        ),
                        'text/html'
                    );
                }
                catch (Swift_TransportException $STe) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - ' . $STe->getMessage() . PHP_EOL;
                    echo $string;
                    // send error note to user
                    echo "the mail service has encountered a problem. Please retry later or contact the site admin.";
                }
                catch (Exception $e) {
                    // logging error
                    $string = date("Y-m-d H:i:s")  . ' - GENERAL ERROR - ' . $e->getMessage() . PHP_EOL;
                    echo $string;
                    // redirect to error page
                    $app->abort(500, "Oops, something went seriously wrong. Please retry later !");
                }

                $this->get( 'mailer' )->send( $message );
                //var_dump($winnerEntry);

            } else if ($response['status']=="warning"){
                //CASE 2: seller did not sell the item because no one bidded
                //CASE 3: the highest bid didnt meet the reserved price
                //send seller unsold email
                $sellerEntry = $this->get( 'db' )->selectOne( $connection, "user", $auction->sellerID);
                $name = $sellerEntry["name"];
                $email = $sellerEntry["email"];
                $reason = "";
                if ($response['message']=="reserved price unmet"){
                    $reason = "The highest bid did not meet the reserved price you have set";
                } else if ($response['message']=="no bid"){
                    $reason = "No one bidded your auction";
                }
                //send email
                $message = \Swift_Message::newInstance()
                ->setSubject( 'You'.$auction->item->itemName.' was not sold!' )
                ->setFrom( 'boobooauction@gmail.com' )
                ->setTo( $email )
                ->setBody(
                    $this->renderView(
                        'Emails/unsold.html.twig',
                        array( 'name' => $name,
                            'auctionID'=>$auctionID,
                            'itemName'=>$auction->item->itemName,
                            'reason'=>$reason )
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );
                if ($response['message']=="reserved price unmet"){
                    //CASE 3:
                    //send winner rpnm emails
                    $winnerEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["winnerID"]);
                    $wname = $winnerEntry["name"];
                    $wemail = $winnerEntry["email"];
                    //send email
                    $message = \Swift_Message::newInstance()
                    ->setSubject( 'You did not meet reserved price for '.$auction->item->itemName.'!' )
                    ->setFrom( 'boobooauction@gmail.com' )
                    ->setTo( $wemail )
                    ->setBody(
                        $this->renderView(
                            'Emails/reservedPriceNotMeet.html.twig',
                            array( 'name' => $wname,
                                'auctionID'=>$auctionID,
                                'itemName'=>$auction->item->itemName )
                        ),
                        'text/html'
                    );
                    $this->get( 'mailer' )->send( $message );
                }
            } 
            $auction->ended = true;
            $this->get('db')->updateAuction($connection,$auction);
        }

        return new JsonResponse( ['status'=>'success'] );



    }

}
