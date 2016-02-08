<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ItemController extends Controller {

    /**
     *
     *
     * @Route("/item/{itemId}", name="item_show", requirements={"itemId": "\d+"})
     */
    public function showAction($itemId) {
        $con = $this->get( "db" )->connect();
        $item = $this->get( "db" )->selectOne( $con, 'item', $itemId );

        //TODO: item does not belong to user, go back
        //TODO: user has not logged in, go back
        // $params = $this->getRefererParams();
        // return $this->redirect($this->generateUrl(
        //     $params['_route']
        // ));
        
        return $this->render('item/show.html.twig', array("item" => $item));
    }


}

?>
