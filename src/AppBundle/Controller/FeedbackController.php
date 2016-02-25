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
     * @Route("/feedback/{auctionID}/new", name="feedback_new", requirements={"auctionID": "\d+"})
     */
    public function newAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        //get auction
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );

        //if auction has not ended yet , redirect to home page
        if ($auction->endAt > date( "Y-m-d H:i:s" )){
            $this->addFlash(
                'warning',
                'This auction has not ended yet!'

            );

            return $this->redirectToRoute( 'homepage' );
        }


        $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auction->itemID );
        $item = new Item( $itemEntry );
        $auction->item = $item;

        //get userID from session, if not logged in, redirect to login
        $session = $request->getSession();
        $userID = $session->get( 'userID' );

        //get receiverID
        $receiverID = $userID == $auction->sellerID ? $auction->winnerID : $auction->sellerID;

        //check if giver is eligible
        $testGiverID = $receiverID == $auction->sellerID ? $auction->winnerID : $auction->sellerID;
        if ($testGiverID != $userID){
            $this->addFlash(
                'warning',
                'You are not eligible to leave feedback for this auction!'

            );

            return $this->redirectToRoute( 'homepage' );
        }

        //get giver and receiver
        $giverEntry = $this->get("db")->selectOne($connection, 'user', $userID);
        $receiverEntry = $this->get("db")->selectOne($connection, 'user', $receiverID);
        $giver = new User($giverEntry);
        $receiver = new User($receiverEntry);    

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
                    'You have left feedback already!'

                );
              return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
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

        $this->get('dump')->d($auction->item);
        $this->get('dump')->d($giver);
        $this->get('dump')->d($receiver);

        return $this->render( 'feedback/new.html.twig', array( "form" => $form->createView(), "auction"=> $auction, 
                                                                "giver"=>$giver, "receiver"=> $receiver ) );
    }

    /**
     *
     *
     * @Route("/feedback/{auctionID}", name="feedback_show", requirements={"auctionID": "\d+"})
     */
    public function showAction( $auctionID, Request $request ) {
        $connection = $this->get( "db" )->connect();
        //get auction
        $auctionEntry = $this->get( "db" )->selectOne( $connection, 'auction', $auctionID );
        $auction = new Auction( $auctionEntry );

        //if auction has not ended yet , redirect to home page
        if ($auction->endAt > date( "Y-m-d H:i:s" )){
            $this->addFlash(
                'warning',
                'This auction has not ended yet!'

            );

            return $this->redirectToRoute( 'homepage' );
        }


        $itemEntry = $this->get( "db" )->selectOne( $connection, 'item', $auction->itemID );
        $item = new Item( $itemEntry );
        $auction->item = $item;

        //get userID from session, if not logged in, redirect to login
        $session = $request->getSession();
        $userID = $session->get( 'userID' );

        //get receiverID
        $receiverID = $userID == $auction->sellerID ? $auction->winnerID : $auction->sellerID;

        //check if giver is eligible
        $testGiverID = $receiverID == $auction->sellerID ? $auction->winnerID : $auction->sellerID;
        if ($testGiverID != $userID){
            $this->addFlash(
                'warning',
                'You are not eligible to leave feedback for this auction!'

            );

            return $this->redirectToRoute( 'homepage' );
        }

        //get giver and receiver
        $giverEntry = $this->get("db")->selectOne($connection, 'user', $userID);
        $receiverEntry = $this->get("db")->selectOne($connection, 'user', $receiverID);
        $giver = new User($giverEntry);
        $receiver = new User($receiverEntry);    

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
                    'You have left feedback already!'

                );
              return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
            }
        }

        //get feedback
        $feedbackEntry = $this->get('db')->selectOne($connection,'feedback',$userID);
        $feedback = new Feedback($feedbackEntry);

        $this->get('dump')->d($auction->item);
        $this->get('dump')->d($giver);
        $this->get('dump')->d($receiver);
        $this->get('dump')->d($feedback);

        return $this->render( 'feedback/show.html.twig', array( "form" => $form->createView(), "auction"=> $auction, 
                                                                "giver"=>$giver, "receiver"=> $receiver,
                                                                "feedback"=>$feedback ) );
    }

}

?>
