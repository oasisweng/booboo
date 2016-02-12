<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        
        $connection = $this->get('db')->connect();
        $hot_auctions = $this->get('db')->getHotAuctions($connection,10);
        $expiring_auctions = $this->get('db')->getExpiringAuctions($connection,10);
        $new_auctions = $this->get('db')->getNewAuctions($connection,10);
        $recommended_auctions = $this->get('db')->getNewAuctions($connection,10);
        return $this->render('default/index.html.twig', array(
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'hot_auctions' => $hot_auctions,
            'expiring_auctions' => $expiring_auctions,
            'new_auctions' => $new_auctions,
            'recommended_auctions' => $recommended_auctions
        ));
    }
}
