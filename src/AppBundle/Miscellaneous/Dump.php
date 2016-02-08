<?php

namespace AppBundle\Miscellaneous;
use Symfony\Component\Security\Core\Util\SecureRandom;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class Dump {
  private $container;

  public function __construct( Container $container ) {
    $this->container = $container;
  }

  public function d($object){
    echo '<pre>'; 
    var_dump($object);
    echo '</pre>';
  }
}
