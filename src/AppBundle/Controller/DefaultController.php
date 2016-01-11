<?php

namespace AppBundle\Controller;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig');
    }

    public function convocantesAction(Request $request)
    {
        $datatableParams = $request->query->all();
        $queryParams = $this->getParams($request);

        /***
         * @var Client
         */
        $client = $this->get('csa_guzzle.client.api');

        /***
         * @var ResponseInterface
         */
        $response = $client->get(
            '/dncpws/publico/parametros/convocantes',
            array(
                'query' => $queryParams,
            )
        );

        $serviceResponse = json_decode($response->getBody(), true);

        $ret = array(
            'raw' => $datatableParams['draw'],
            'recordsTotal' => $serviceResponse['pagination']['total_items'],
            'recordsFiltered' => $serviceResponse['pagination']['total_items'],
            'data' => $serviceResponse['list'],
        );

        if($request->query->get('debug')) {
            dump($datatableParams);
            dump($queryParams);
            dump($serviceResponse);
            dump($ret);

            return new Response('');
        }

        return new JsonResponse($ret);
    }

    private function getParams(Request $request)
    {
        $datatableParams = $request->query->all();

        $ret = array(
            'offset' => $datatableParams['start'],
            'limit' => $datatableParams['length'],
            'order' => $this->getOrder($datatableParams),
        );

        if($filters = $this->getFilters($datatableParams)){
            $ret = array_merge($ret, $filters);
        }

        return $ret;
    }

    private function getOrder($datatableParams)
    {
        $ret = array();

        foreach($datatableParams['order'] as $order){
            $ret[] = $datatableParams['columns'][$order['column']]['data'].' '.$order['dir'];
        }

        return implode($ret, ',');
    }

    private function getFilters($datatableParams)
    {
        $ret = array();

        foreach($datatableParams['columns'] as $column){
            if($column['search']['value']){
                $ret[$column['data']] = $column['search']['value'];
            }
        }

        return $ret;
    }
}
