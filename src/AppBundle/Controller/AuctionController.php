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
     * @Route("/user/{userId}/auction/new", name="auction_new", requirements={"userId": "\d+"})
     */
    public function newAction( $userId, Request $request ) {
        $auction = new Auction();
        $auction->sellerID = $userId;
        $form = $this->createForm( AuctionType::class, $auction );


        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... perform some action, such as saving the task to the database
            $connection = $this->get( 'db' )->connect();
            if ( $auctionId = $this->get( 'db' )->addAuction( $connection, $auction ) ) {
                $this->addFlash(
                    'notice',
                    'New Auction created!'
                );

                return $this->redirectToRoute( 'auction_show', array( "auctionId"=>$auctionId ), 301 );
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
     * @Route("/auction/{auctionId}", name="auction_show", requirements={"auctionId": "\d+"})
     */
    public function showAction( $auctionId ) {
        $con = $this->get( "db" )->connect();
        $auction = $this->get( "db" )->selectOne( $con, 'auction', $auctionId );
        var_dump( $auction );
        return $this->render( 'auction/show.html.twig', array( "auction"=>$auction ) );
    }

    /**
     *
     *
     * @Route("/auction/{auctionId}/edit", name="auction_edit", requirements={"auctionId": "\d+"})
     */
    public function editAction( $auctionId, Request $request ) {

        return $this->render( 'auction/edit.html.twig' );
    }

    /**
     *
     *
     * @Route("/auction/{auctionId}/editt", name="auction_edit_test", requirements={"auctionId": "\d+"})
     */
    public function editTestAction( $auctionId, Request $request ) {
        $connection = $this->get( "db" )->connect();
        if ( $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionId ) ) {
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
                        'Auction {$auctionId} updated!'
                    );

                    return $this->redirectToRoute( 'auction_show', array( "auctionId"=>$auctionId ), 301 );
                } else {
                    $this->addFlash(
                        'error',
                        'Updating Auction {$auctionId} went wrong! AuctionController.php'
                    );
                }
            }

            return $this->render( 'auction/editt.html.twig', array(
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
