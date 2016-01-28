<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


//test form
use AppBundle\Entity\Task;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


class AuctionController extends Controller {
    /**
     *
     *
     * @Route("/user/{userId}/auction/new", requirements={"userId": "\d+"})
     */
    public function newAction($userId,Request $request ) {
        $task = new Task();

        $form = $this->createFormBuilder( $task )
        ->add( 'task', TextType::class )
        ->add( 'dueDate', DateType::class )
        ->add( 'save', SubmitType::class, array( 'label' => 'Create Task' ) )
        ->getForm();

        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... perform some action, such as saving the task to the database

            return $this->redirectToRoute( 'task_success' );
        }

        return $this->render( 'auction/new.html.twig', array(
                'form' => $form->createView(),
            ) );
    }

    /**
     *
     *
     * @Route("/auction/{auctionId}", requirements={"auctionId": "\d+"})
     */
    public function showAction($auctionId) {

        return $this->render( 'auction/show.html.twig');
    }

    /**
     *
     *
     * @Route("/auction/{auctionId}/edit", requirements={"auctionId": "\d+"})
     */
    public function editAction($auctionId,Request $request) {
        $task = new Task();

        $form = $this->createFormBuilder( $task )
        ->add( 'task', TextType::class )
        ->add( 'dueDate', DateType::class )
        ->add( 'save', SubmitType::class, array( 'label' => 'Create Task' ) )
        ->getForm();

        $form->handleRequest( $request );

        if ( $form->isSubmitted() && $form->isValid() ) {
            // ... perform some action, such as saving the task to the database

            return $this->redirectToRoute( 'task_success' );
        } else {
            // ... fill with exisiting data
        }

        return $this->render( 'auction/edit.html.twig', array(
                'form' => $form->createView(),
            ) );
    }


}
