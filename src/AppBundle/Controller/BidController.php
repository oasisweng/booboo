<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


use AppBundle\Entity\Auction;
use AppBundle\Entity\Bid;
use AppBundle\Form\Type\BidType;

class BidController extends Controller {

    /**
     *
     *
     * @Route("/auction/{auctionID}/bid", name="bid_bid", requirements={"auctionID": "\d+"})
     */
    public function bidAction( $auctionID, Request $request ) {
        $bid = new Bid();
        //get connection
        $connection = $this->get( 'db' )->connect();
        //get auction
        $auctionEntity = $this->get( 'db' )->selectOne( $connection, "auction", $auctionID );
        if ( !$auctionEntity ) {
            die( "retrieve auction failed in bidAction" );
        }

        $auction = new Auction( $auctionEntity );
        $bid->auctionID = $auctionID;
        //get userID
        $userID = 9;//will use session
        if ( !isset( $userID ) ) {
            //return to login page
            $this->addFlash(
                'error',
                'You need to login first!'
            );

        } else if ( $userID == $auction->sellerID ) {
                //return to auction page
                $this->addFlash(
                    'error',
                    'You can\'t bid on your auction.' );
                return true;
            }
        $bid->buyerID = $userID;


        $form = $this->createForm( BidType::class, $bid );

        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {

            //attempt to bid
            $response = $this->get( 'db' )->bid( $connection, $bid, $auction );

            var_dump( $response );

            if ( $response["status"] ) {
                $this->addFlash(
                    'notice',
                    $response["message"]
                );

                return $this->render( 'bid/bid.html.twig', array(
                        'form' => $form->createView(),
                    ) );
                return $this->redirectToRoute( 'auction_show', array( "auctionID"=>$auctionID ), 301 );
            } else {
                $this->addFlash(
                    'error',
                    $response["message"]
                );
            }

        }

        return $this->render( 'bid/bid.html.twig', array(
                'form' => $form->createView(),
            ) );
    }

}
