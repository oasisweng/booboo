<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


class LuckyController extends Controller {
    /**
     *
     *
     * @Route("/lucky/number/{count}")
     */
    public function numberAction( $count ) {
        $numbers = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $numbers[] = rand( 0, 100 );
        }
        $numbersList = implode( ', ', $numbers );

        // $html = $this->container->get('templating')->render(
        //     'lucky/number.html.twig',
        //     array('luckyNumberList' => $numbersList)
        // );

        // return new Response($html);

        $con = $this->get( "db" )->connect();
        $item = $this->get( "db" )->selectOne( $con, 'item', 1 );

        $itemName = "Victo'ri\$q;@";
        $description = 'Victoria\' secret';
        $categoryID = 2;

        if ( $id =  $this->get( "db" )->addItem( $con ,  $itemName, $description, NULL, NULL, $categoryID ) ) {
            echo "ADD SUCCESS. id => " . $id;
            if ( $this->get( "db" )->deleteOne( $con, 'item', $id ) ) {
                echo "<br/>DELETE SUCCESS. ";
            }
        }
        mysqli_close( $con );

        return $this->render(
            'lucky/number.html.twig',
            array( 'luckyNumberList' => $numbersList, 'item' => $item )
        );
    }

    /**
     *
     *
     * @Route("/api/lucky/number")
     */
    public function apiNumberAction() {
        $data = array(
            'lucky_number' => rand( 0, 100 ),
        );

        return new JsonResponse( $data );
    }
}
