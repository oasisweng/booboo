<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


//test form
use AppBundle\Form\UserType;
use AppBundle\Entity\User;


class UserController extends Controller {
    /**
     *
     *
     * @Route("/register", name="user_registration")
     */
    public function registerAction( Request $request ) {
        // // 1) build the form
        // $user = new User();
        // $form = $this->createForm( UserType::class, $user);

        // // 2) handle the submit (will only happen on POST)
        // $form->handleRequest( $request );

        // if ( $form->isSubmitted() && $form->isValid() ) {
        //     // ... do any other work - like send them an email, etc
        //     // maybe set a "flash" success message for the user

        //     if ( $userId = $this->get( 'db' )->addUser( $connection, $user ) ) {
        //         $this->addFlash(
        //             'notice',
        //             'New User created!'
        //         );

        //         return $this->redirectToRoute('homepage', 302);
        //     } else {
        //         $this->addFlash(
        //             'error',
        //             'Creating user went wrong! UserController.php'
        //         );
        //     }
        // }


        return $this->render(
            'user/new.html.twig');//,
        //    array( 'form' => $form->createView() )
       // );
    }

    /**
     *
     *
     * @Route("/login", name="user_login")
     */
    public function loginAction( Request $request ) {
        // 1) build the form
        $user = new User();
        $form = $this->createForm( UserType::class, $user);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... do any other work - like send them an email, etc
            // maybe set a "flash" success message for the user

            if ($id = $this->get( 'db' )->login( $user ) ) {
                $this->addFlash(
                    'notice',
                    'Log in!'
                );
                $session = new Session();

                $userAttributeBag = new AttributeBag('user');
                $session->registerBag($userAttributeBag);

                $session->start();

                $userAttributeBag->set('userId','{$id}');

                return $this->redirectToRoute('homepage', 302);
            } else {
                $this->addFlash(
                    'error',
                    'Login failed! UserController.php'
                );
            }
        }


        return $this->render(
            'user/new.html.twig');//,
         //   array( 'form' => $form->createView() )
       // );
    }

    /**
     *
     *
     * @Route("/user/{userId}", name="user_show", requirements={"userId": "\d+"})
     */
    public function showAction($userId) {
        // $con = $this->get( "db" )->connect();
        // $user = $this->get( "db" )->selectOne( $con, 'user', $userId );
        // var_dump($user);
        return $this->render( 'user/show.html.twig');//, array("user"=>$user) );
    }

    /**
     *
     *
     * @Route("/user/{userId}/edit", name="user_edit",  requirements={"userId": "\d+"})
     */
    public function editAction( $userId, Request $request ) {
        // // 1) build the form
        // $user = new User();
        // $form = $this->createForm( UserType::class, $user );

        // // 2) handle the submit (will only happen on POST)
        // $form->handleRequest( $request );
        // if ( $form->isSubmitted() && $form->isValid() ) {
        //     // ... do any other work - like send them an email, etc
        //     // maybe set a "flash" success message for the user

        //     return $this->redirectToRoute( 'replace_with_some_route' );
        // } else {
        //     // 2.5) fill with exisiting data
        // }

        return $this->render(
            'user/edit.html.twig');//,
        //    array( 'form' => $form->createView() )
        //);
    }


}
