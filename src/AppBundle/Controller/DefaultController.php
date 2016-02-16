<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $session = $request->getSession();        
        $connection = $this->get('db')->connect();
        $hot_auctions = $this->get('db')->getHotAuctions($connection,10);
        $expiring_auctions = $this->get('db')->getExpiringAuctions($connection,10);
        $new_auctions = $this->get('db')->getNewAuctions($connection,1,10,false);

        $recommended_auctions = [];

        $session = $request->getSession();
        $userID = $session->get('userID');
        if (!is_null($userID)){
            $recommended_auctions = $this->get('db')->getRecommendedAuctions($connection,$userID);  
        }
        
        //use this code to see what is in hot auctions
        // $this->get('dump')->d($hot_auctions);
        return $this->render('default/index.html.twig', array(
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'hot_auctions' => $hot_auctions,
            'expiring_auctions' => $expiring_auctions,
            'new_auctions' => $new_auctions,
            'recommended_auctions' => $recommended_auctions,
            'is_logged_in' => $session->get('userID')
        ));
    }
}
