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
    public function showAction( $auctionID ) {
        $connection = $this->get( "db" )->connect();
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );

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

        $params = array( "auction"=>$auction,
            "ended"=>$ended,
            "bidded"=>$bidded,
            "won"=>$won,
            "winning"=>$winning );

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
