<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


use AppBundle\Entity\Auction;
use AppBundle\Entity\Item;
use AppBundle\Form\Type\AuctionType;

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
            echo $totalPages;
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
     * @Route("/user/{userID}/auction/new", name="auction_new", requirements={"userID": "\d+"})
     */
    public function newAction( $userID, Request $request ) {
        $auction = new Auction();
        $auction->sellerID = $userID;
        $form = $this->createForm( AuctionType::class, $auction );


        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... perform some action, such as saving the task to the database
            $connection = $this->get( 'db' )->connect();
            if ( $auctionID = $this->get( 'db' )->addAuction( $connection, $auction ) ) {
                $this->addFlash(
                    'notice',
                    'New Auction created!'
                );

                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            } else {
                $this->addFlash(
                    'error',
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

        //if should finish auction through this way
        if ( $this->get( 'db' )->shouldFinishAuction( $auction ) ) {
            $this->get( 'db' )->finishAuction( $connection, $auction );
        }

        //get userID, or null if not logged in
        $userID = 1234;

        $ended = $auction->ended;
        //placed a bid
        $bidded = isset( $userID ) && $this->get( 'db' )->bidded( $connection, $auctionID, $userID );
        $winning = $bidded && $auction->winnerID==$userID;
        $won = $ended && $winning;

        //bid form
        $bid = new Bid();
        $bid->auctionID = $auctionID;
        //get userID
        $userID = 9;//will use session
        if ( !isset( $userID ) ) {
            //return to login page
            $this->addFlash(
                'error',
                'You need to login first!'
            );

            return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ) ), 301 );
        } else if ( $userID == $auction->sellerID ) {
                //return to auction page
                $this->addFlash(
                    'error',
                    'You can\'t bid on your auction.' );
            }

        $bid->buyerID = $userID;

        $bidForm = $this->createForm( BidType::class, $bid );

        $bidForm->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {

            //attempt to bid
            $response = $this->get( 'db' )->bid( $connection, $bid, $auction );

            if ( $response["status"] ) {
                $this->addFlash(
                    'notice',
                    $response["message"]
                );
                //get the user who has been outbid, send an email
                if (!is_null($response["second_buyerID"])){
                    //if someone has been outbid and its not current buyer, get user name
                    $userEntry = $this->get('db')->selectOne($connection,"user",$response["second_buyerID"]);
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
                        'auctionID'=>$auctionID)
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );
                }

               

                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            } else {
                $this->addFlash(
                    'error',
                    $response["message"]
                );
            }

        }

        $params = array( "auction"=>$auction,
            "ended"=>$ended,
            "bidded"=>$bidded,
            "won"=>$won,
            "winning"=>$winning,
            'bid_form' => $bidForm->createView(), );

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
            $this->get( 'dump' )->d( $auctionEntry );
            $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auctionEntry["itemID"] );
            $auction = new Auction( $auctionEntry );
            $auction->item = new Item( $itemEntry );

            $form = $this->createForm( AuctionType::class, $auction );;

            $form->handleRequest( $request );

            if ( $form->isSubmitted() && $form->isValid() ) {
                if ( $this->get( 'db' )->updateAuction( $connection, $auction ) ) {
                    $this->addFlash(
                        'notice',
                        'Auction {$auctionID} updated!'
                    );

                    return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
                } else {
                    $this->addFlash(
                        'error',
                        'Updating Auction {$auctionID} went wrong! AuctionController.php'
                    );
                }
            }

            return $this->render( 'auction/edit.html.twig', array(
                    'form' => $form->createView(),
                ) );
        } else {
            $this->addFlash(
                'error',
                'The auction selected does not exist!'
            );

            return $this->redirectToRoute( 'homepage' );
        }
    }

}
