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
use AppBundle\Form\Type\BidType;

class AuctionController extends Controller {

    /**
     *  get search result based on keywords and page number
     *
     * @Route("/auctions/{page}", name = "auction_search",
     *                            requirements = {"page": "\d+"},
     *                            defaults = {"page" : 1})
     */
    public function searchAction( $page, Request $request ) {
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
        $keywords_s = ltrim( rtrim( $request->get( 'keywords', '' ) ) );
        $keywords_a = [];
        if ( strlen( $keywords_s )>0 ) {
            $keywords_a = explode( ' ', $keywords_s );
        }

        $connection = $this->get( 'db' )->connect();
        $searchResults = $this->get( 'db' )->searchAuctions( $connection, $keywords_a, $page, 25 );

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
            // echo $totalPages;
            $this->get( 'dump' )->d( $searchResults );
            $auctions = [];
            $items= [];
            foreach ( $searchResults["auctions"] as $auctionEntry ) {
                $auctions[] = new Auction( $auctionEntry );
                $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auctionEntry["itemID"] );
                $items[] = new Item( $itemEntry );
            }

            return $this->render( 'auction/search.html.twig', array(
                    'totalPages' => $totalPages,
                    'auctions' => $auctions,
                    'items' => $items,
                    'page' => $page
                ) );
        }
    }

    /**
     *
     *
     * @Route("auction/new", name="auction_new")
     */
    public function newAction( $userID, Request $request ) {
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
            $connection = $this->get( 'db' )->connect();
            if ( $auctionID = $this->get( 'db' )->addAuction( $connection, $auction ) ) {
                $this->addFlash(
                    'success',
                    'New Auction created!'
                );

                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            } else {
                $this->addFlash(
                    'warning',
                    'Creating auction went wrong! AuctionController.php'
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
     * @Route("/auction/{auctionID}", name="auction_show", requirements={"auctionID": "\d+"})
     */
    public function showAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );
        $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auction->itemID );
        $item = new Item( $itemEntry );
        $auction->item = $item;

        $sellerEntry = $this->get( "db" )->selectOne( $connection, 'user', $auction->sellerID );
        $seller = new User($sellerEntry);

        //if should finish auction through this way
        if ( $this->get( 'db' )->shouldFinishAuction( $auction ) ) {
            $this->get( 'db' )->finishAuction( $connection, $auction );
        }

        //get userID, or null if not logged in
        $session = $request->getSession();   
        $userID = $session->get('userID');


        $ended = $auction->ended;
        $bidded = isset( $userID ) && $this->get( 'db' )->bidded( $connection, $auctionID, $userID );
        $winning = $bidded && $auction->winnerID==$userID;
        $won = $ended && $winning;

        //get all bids for this auction
        $bidEntries = $this->get('db')->getAllBids($connection,$auctionID);
        //$this->get('dump')->d($bidEntries);
        $bids = [];
        foreach ($bidEntries as $bidEntry){
            if ($bidEntry){
                $bids[] = new Bid($bidEntry);    
            }
        }

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
                return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ) ), 301 );
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
                $this->get( 'dump' )->d( $response );
                if ( $response["status"] == "success" ) {
                    $this->addFlash(
                        'success',
                        $response["message"]
                    );
                    //get the user who has been outbid, send an email
                    if ( array_key_exists( "second_buyerID", $response ) ) {

                        echo "second buyer ID is ".$response["second_buyerID"];
                        //if someone has been outbid and its not current buyer, get user name
                        $userEntry = $this->get( 'db' )->selectOne( $connection, "user", $response["second_buyerID"] );
                        $name = $userEntry["name"];
                        $email = $userEntry["email"];
                        $this->get( 'dump' )->d( $userEntry );
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

        //prepare return objects
        $params = array( "auction"=>$auction,
            "ended"=>$ended,
            "bidded"=>$bidded,
            "won"=>$won,
            "winning"=>$winning,
            "bid_form" => $bidForm->createView(),
            "bids" => $bids,
            "seller" => $seller );

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

            $this->get( 'dump' )->d( $auctionEntry );
            $auction = new Auction( $auctionEntry );

            if ($auction->endAt<date("Y-m-d H:i:s")) {
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
                        'Auction {$auctionID} updated!'
                    );

                    return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
                } else {
                    $this->addFlash(
                        'warning',
                        'Updating Auction {$auctionID} went wrong! AuctionController.php'
                    );
                }
            }

            return $this->render( 'auction/edit.html.twig', array(
                    'form' => $form->createView(),
                ) );
        } else {
            $this->addFlash(
                'warning',
                'The auction selected does not exist!'
            );

            return $this->redirectToRoute( 'homepage' );
        }
    }

}
