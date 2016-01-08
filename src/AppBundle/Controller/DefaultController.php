<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/categorias.json", name="categorias")
     */
    public function categoriasAction(Request $request)
    {
        $client = $this->get('csa_guzzle.client.api');

        $response = $client->get('/dncpws/publico/parametros/categorias');

        $ret = array(
            'data' => $response->getBody()
        );

        return new Response(json_encode($ret));
    }
}
