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

        return $this->render('item/show.html.twig');
    }


}

?>
