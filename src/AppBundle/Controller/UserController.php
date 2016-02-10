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
                    'notice',
                    'New User created!'
                );

                return $this->redirectToRoute( 'homepage', array(), 301 );
            } else {
                $this->addFlash(
                    'error',
                    'Creating user went wrong! UserController.php'
                );
                die("failed to add user");
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
     * @Route("/login", name="user_login")
     */
    public function loginAction( Request $request ) {
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
                    'notice',
                    'Log in!'
                );
                $session = $request->getSession();

                $userAttributeBag = new AttributeBag( 'user' );
                $session->registerBag( $userAttributeBag );

                $userAttributeBag->set( 'userID', '{$id}' );

                return $this->redirectToRoute( 'homepage', array(), 301 );
            } else {
                $this->addFlash(
                    'error',
                    'Login failed! UserController.php'
                );
            }
        }


        return $this->render(
            'user/login.html.twig',
            array( 'form' => $form->createView() )
        );
    }

    /**
     *
     *
     * @Route("/user/{userID}", name="user_show", requirements={"userID": "\d+"})
     */
    public function showAction( $userID ) {
        $con = $this->get( "db" )->connect();
        $user = $this->get( "db" )->selectOne( $con, 'user', $userID );
        var_dump( $user );
        return $this->render( 'user/show.html.twig', array( "user"=>$user ) );
    }

    /**
     *
     *
     * @Route("/user/{userID}/edit", name="user_edit",  requirements={"userID": "\d+"})
     */
    public function editAction( $userID, Request $request ) {
        //edit user name
        return $this->render(
            'user/edit.html.twig'
        );
    }


    /**
     *
     *
     * @Route("/user/{userID}/update_profile", name="user_update_profile",  requirements={"userID": "\d+"})
     */
    public function updateProfileAction( $userID, Request $request ) {
        // 1) build the form
        $connection = $this->get( "db" )->connect();
        $userEntry = $this->get( "db" )->selectOne( $connection, "user", $userID );
        if ( isset($userEntry) ) {
            $user = new User( $userEntry );
            $form = $this->createForm( UpdateProfileType::class, $user );

            // 2) handle the submit (will only happen on POST)
            $form->handleRequest( $request );
            if ( $form->isSubmitted() && $form->isValid() ) {
                // ... do any other work - like send them an email, etc
                // maybe set a "flash" success message for the user
                if ( $this->get( 'db' )->updateUser( $connection, $user ) ) {
                    $this->addFlash(
                        'notice',
                        'User {$userID} profile updated!'
                    );

                    return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
                } else {
                    $this->addFlash(
                        'error',
                        'Updating user went wrong! UserController.php'
                    );

                }

                return $this->render(
                    'user/update_profile.html.twig',
                    array( 'form' => $form->createView() )
                );
            } else {
                return $this->render(
                    'user/update_profile.html.twig',
                    array( 'form' => $form->createView() )
                );
            }
        } else {
                $this->addFlash(
                    'error',
                    'The user selected does not exist!'
                );

                return $this->redirectToRoute( 'homepage' );
            }

    }

    /**
     *
     *
     * @Route("/user/{userID}/change_password", name="user_change_password",  requirements={"userID": "\d+"})
     */
    public function changePasswordAction( $userID, Request $request ) {
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
                        'notice',
                        'User {$userID} password reset!'
                    );

                    return $this->redirectToRoute( 'user_show', array( "userID"=>$userID ), 301 );
                } else {
                    $this->addFlash(
                        'error',
                        'Resetting user went wrong! UserController.php'
                    );

                }

                return $this->render(
                    'user/forgotten_password.html.twig',
                    array( 'form' => $form->createView() )
                );
            } else {
                return $this->render(
                    'user/forgotten_password.html.twig',
                    array( 'form' => $form->createView() )
                );
            }
        } else {
                $this->addFlash(
                    'error',
                    'The user selected does not exist!'
                );

                return $this->redirectToRoute( 'homepage' );
            }

    }
}
