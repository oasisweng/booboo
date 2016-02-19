<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;


//test form
use AppBundle\Form\Type\UserType;
use AppBundle\Form\Type\UpdateProfileType;
use AppBundle\Form\Type\ChangePasswordType;
use AppBundle\Form\Type\LoginType;
use AppBundle\Entity\User;
use AppBundle\Entity\Auction;


class UserController extends Controller {

    /**
     *
     *
     * @Route("/register", name="user_registration")
     */
    public function registerAction( Request $request ) {
        // 1) build the form
        $user = new User();
        $form = $this->createForm( UserType::class, $user );

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest( $request );
        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... do any other work - like send them an email, etc
            // maybe set a "flash" success message for the user

            if ( $userID = $this->get( 'db' )->addUser( $user ) ) {
                $this->addFlash(
                    'success',
                    'We sent you an welcome email!'
                );

                //send notification email on successful registration
                $name = $user->name;
                $email = $user->email;
                //send email
                $message = \Swift_Message::newInstance()
                ->setSubject( 'Welcome to Booboo Auction!' )
                ->setFrom( 'boobooauction@gmail.com' )
                ->setTo( $email )
                ->setBody(
                    $this->renderView(
                        'Emails/registration.html.twig',
                        array( 'name' => $name )
                    ),
                    'text/html'
                );
                $this->get( 'mailer' )->send( $message );

                $session = $request->getSession();
                $session->set( 'userID', $userID );

                return $this->redirectToRoute( 'homepage', array(), 301 );
            } else {
                $this->addFlash(
                    'danger',
                    'Creating user went wrong! UserController.php'
                );
                die( "failed to add user" );
            }
        }

        return $this->render(
            'user/new.html.twig',
            array( 'form' => $form->createView() )
        );
    }

    /**
     *
     *
     * @Route("/logout", name="user_logout")
     */
    public function logoutAction( Request $request ) {
        $session = $request->getSession();
        $session->set( 'userID', null );
        return $this->redirectToRoute( 'homepage' );
    }

    /**
     *
     *
     * @Route("/login", name="user_login")
     */
    public function loginAction( Request $request ) {
        $session = $request->getSession();
        if ( $session->get( 'userID' ) ) {
            return $this->redirectToRoute( 'homepage', array(), 301 );
        }

        // 1) build the form
        $user = new User();
        $form = $this->createForm( LoginType::class, $user );

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... do any other work - like send them an email, etc
            // maybe set a "flash" success message for the user

            if ( $id = $this->get( 'db' )->login( $user ) ) {
                $this->addFlash(
                    'success',
                    'Log in!'
                );
                $session = $request->getSession();

                $session->set( 'userID', $id );


                $redirectRoute = $request->get( 'redirectRoute' );
                $params = $request->get( 'params' );
                if (is_null($params)){
                    $params = array();
                }
                if ( isset( $redirectRoute ) ) {
                    return $this->redirectToRoute( $redirectRoute, $params, 301 );
                }
                return $this->redirectToRoute( 'homepage', array(), 301 );
            } else {
                $this->addFlash(
                    'warning',
                    'You have entered wrong username/password!'
                );
            }
        }

        $session = $request->getSession();

        return $this->render(
            'user/login.html.twig',
            array( 'form' => $form->createView(),  'is_logged_in' => $session->get( 'userID' ) )
        );
    }

    /**
     *
     *
     * @Route("/user/{userID}", name="user_show", requirements={"userID": "\d+"})
     *
     * @return  'buyingArray' An array of Auction objects
     * @return  'sellingArray' An array of Auction objects
     * @return  'boughtArray' ["auction" : an Auction object, "canFeedback": a boolean indicating whether 
     *                                 user can leave feedback for this auction ]
     * @return  'soldArray' ["auction" : an Auction object, "canFeedback": a boolean indicating whether 
     *                                 user can leave feedback for this auction ]
     * @return  "user" is an User object of this user
     * @return  'owner' is an boolean indicating whether current user owns this user profile
     * @return  'averageRating' is an integer indicating average rating of this user
     */
    public function showAction( $userID, Request $request ) {
        //passing through entire session as parameter instead of just userID
        //entire session required to check whether user is 'LOGGED IN' or 'NOT LOGGED IN'
        $connection = $this->get( "db" )->connect();
        $userEntry = $this->get( "db" )->selectOne( $connection, 'user', $userID );
        $user = new User( $userEntry );

        if ( !$user ) {
            return $this->redirectToRoute( 'homepage' );
        }

        $session = $request->getSession();
        //check if current user owns this profile
        $owner = $session->get( 'userID' ) == $userID ;

        //get average rating and total number of feedbacks received
        $rating = $this->get('db')->getAverageRating($connection,$userID);
        $averageRating = $rating["AvgRating"];


        if ( $owner ) {
            //if current user profile page belongs to current logged-in user, get detailed information

            //get buying auctions
            $buyingEntries = $this->get('db')->getBuyingAuctions($connection,$userID);
            $buying = [];
            foreach ($buyingEntries as $buyingEntry){
                if ($buyingEntry){
                    $buying[] = new Auction($buyingEntry);    
                }
            }

            //get selling
            $sellingEntries = $this->get('db')->getSellingAuctions($connection,$userID);
            $selling = [];
            foreach ($sellingEntries as $sellingEntry){
                if ($sellingEntry){
                    $selling[] = new Auction($sellingEntry);    
                }
            }
            //get bought
            $boughtEntries = $this->get('db')->getBoughtAuctions($connection,$userID);
            $bought = [];
            foreach ($boughtEntries as $boughtEntry){
                if ($boughtEntry){
                    $bought[] = new Auction($boughtEntry);
                    //check if user should leave feedback    
                }
            }
            //get sold
            $soldEntries = $this->get('db')->getSoldAuctions($connection,$userID);
            $sold = [];
            foreach ($soldEntries as $soldEntry){
                if ($soldEntry){
                    $sold[] = new Auction($soldEntry);
                    //check if user should leave feedback    
                }
            }


            return $this->render( "user/show.html.twig", array( 'buyingArray'=>$buying,
                    'sellingArray'=>$selling,
                    'boughtArray'=>$bought,
                    'soldArray'=>$sold,
                    "user"=>$user,
                    'owner' => $owner,
                    'averageRating' => $averageRating ) );
        } else {
            //if current user profile page is other people's, get only selling array
            //get selling
            $sellingEntries = $this->get('db')->getSellingAuctions($connection,$userID);
            $selling = [];
            foreach ($sellingEntries as $sellingEntry){
                if ($sellingEntry){
                    $selling[] = new Auction($sellingEntry);    
                }
            }

            return $this->render( "user/show.html.twig", array( 'sellingArray'=>$selling,
                    "user"=>$user,
                    'owner' => $owner,
                    'averageRating' => $averageRating ) );
        }

    }

    /**
     * @Route("/user/api/{userID}", name="user_api", requirements={"userID": "\d+"})
     */
    public function apiAction( $userID, Request $request ) {
        //passing through entire session as parameter instead of just userID
        //entire session required to check whether user is 'LOGGED IN' or 'NOT LOGGED IN'
        $connection = $this->get( "db" )->connect();
        $userEntry = $this->get( "db" )->selectOne( $connection, 'user', $userID );
        $user = new User( $userEntry );

        if ( !$user ) {
            return new Response("Error", 400);
        }

        //get average rating and total number of feedbacks received
        $rating = $this->get('db')->getAverageRating($connection,$userID);
        $averageRating = $rating["AvgRating"];

            //if current user profile page belongs to current logged-in user, get detailed information

            //get buying auctions
            $buyingEntries = $this->get('db')->getBuyingAuctions($connection,$userID);
            $buying = [];
            foreach ($buyingEntries as $buyingEntry){
                if ($buyingEntry){
                    $buying[] = new Auction($buyingEntry);    
                }
            }
            //get selling
            $sellingEntries = $this->get('db')->getSellingAuctions($connection,$userID);
            $selling = [];
            foreach ($sellingEntries as $sellingEntry){
                if ($sellingEntry){
                    $selling[] = new Auction($sellingEntry);    
                }
            }
            //get bought
            $boughtEntries = $this->get('db')->getBoughtAuctions($connection,$userID);
            $bought = [];
            foreach ($boughtEntries as $boughtEntry){
                if ($boughtEntry){
                    $bought[] = new Auction($boughtEntry);
                    //check if user should leave feedback    
                }
            }
            //get sold
            $soldEntries = $this->get('db')->getSoldAuctions($connection,$userID);
            $sold = [];
            foreach ($soldEntries as $soldEntry){
                if ($soldEntry){
                    $sold[] = new Auction($soldEntry);
                    //check if user should leave feedback    
                }
            }


            return new JsonResponse(array( 'buying'=>$buying,
                    'selling'=>$selling,
                    'bought'=>$bought,
                    'sold'=>$sold,
                    'averageRating' => $averageRating ));

    }

    /**
     *
     *
     * @Route("/user/{userID}/change_password", name="user_change_password",  requirements={"userID": "\d+"})
     */
    public function changePasswordAction( $userID, Request $request ) {
        // check login and same user
        $session = $request->getSession();
        $currentUserID = $session->get( 'userID');

        if (is_null($currentUserID)){
            //login first 
            //return to login page
            $this->addFlash(
                'warning',
                'You need to login first!'
            );
            return $this->redirectToRoute( 'user_login', array( "redirectRoute"=>$request->get( '_route' ) ), 301 );
        } else if ($currentUserID != $userID){
            $this->addFlash(
                'danger',
                'You don\'t have rights to change this user\'s password!'
            );
            return $this->redirectToRoute( 'homepage' );
        }

        // 1) build the form
        $connection = $this->get( "db" )->connect();
        if ( $userEntry = $this->get( "db" )->selectOne( $connection, "user", $userID ) ) {
            $user = new User( $userEntry );
            $form = $this->createForm( ChangePasswordType::class, $user );

            // 2) handle the submit (will only happen on POST)
            $form->handleRequest( $request );
            if ( $form->isSubmitted() && $form->isValid() ) {
                // ... do any other work - like send them an email, etc
                // maybe set a "flash" success message for the user

                if ( $this->get( 'db' )->updateUser( $connection, $user ) ) {
                    $this->addFlash(
                        'success',
                        'User {$userID} password reset!'
                    );

                    return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
                } else {
                    $this->addFlash(
                        'warning',
                        'Failed to reset password!'
                    );

                }

                return $this->render(
                    'user/change_password.html.twig',
                    array( 'form' => $form->createView() )
                );
            } else {
                return $this->render(
                    'user/change_password.html.twig',
                    array( 'form' => $form->createView() )
                );
            }
        } else {
            $this->addFlash(
                'danger',
                'The user selected does not exist!'
            );

            return $this->redirectToRoute( 'homepage' );
        }

    }
}
