<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;

use AppBundle\Entity\User;
use AppBundle\Entity\Auction;
use AppBundle\Entity\Feedback;
use AppBundle\Entity\Item;
use AppBundle\Form\Type\FeedbackType;

class FeedbackController extends Controller {

    /**
     *
     *
     * @Route("/feedback/{auctionID}", name="feedback_new", requirements={"auctionID": "\d+"})
     */
    public function newAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        //get auction
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );
        $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auction->itemID );
        $item = new Item( $itemEntry );
        $auction->item = $item;

        //get userID from session, if not logged in, redirect to login
        $session = $request->getSession();
        $userID = $session->get( 'userID' );

        //get receiverID
        $receiverID = $userID == $auction->sellerID ? $auction->winnerID : $auction->sellerID;

        //get giver and receiver
        $giverEntry = $this->get("db")->selectOne($connection, 'user', $userID);
        $receiverEntry = $this->get("db")->selectOne($connection, 'user', $receiverID);
        $giver = new User($giverEntry);
        $receiverEntry = new User($receiverEntry);
        
        if ( !isset( $userID ) ) {
            //return to login page
            $this->addFlash(
                'warning',
                'You need to login first!'
            );
            return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ) ), 301 );
        } else {
            //check user can leave feedback, otherwise, redirect to user_profile
            $canFeedback = $this->get( 'db' )->canFeedback( $connection, $userID, $receiverID, $auctionID );
            if ( !$canFeedback ) {
                $this->addFlash(
                    'warning',
                    'You can\'t leave feedback for this auction!'

                );
              //  return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
            }
        }

        // 1) build the form
        $feedback = new feedback();
        $feedback->giverID = $userID;
        $feedback->receiverID = $receiverID;
        $feedback->auctionID = $auctionID;

        $form = $this->createForm( FeedbackType::class, $feedback );

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... do any other work - like send them an email, etc
            // maybe set a "flash" success message for the user

            $response = $this->get('db')->feedback( $connection, $feedback );
            if ( $response["status"] == "success" )  {
                $this->addFlash(
                    'success',
                    'You left an feedback!'
                );

               
            } else {
                $this->addFlash(
                    'warning',
                    $response["reason"]
                );
            }

            return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
        }



        return $this->render( 'feedback/new.html.twig', array( "form" => $form->createView(), "auction"=> $auction, "giver"=>$giver, "receiver"=> $receiver ) );
    }


}

?>
