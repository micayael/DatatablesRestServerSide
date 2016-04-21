<?php

namespace AppBundle\Controller;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig');
    }

    public function editorAction(Request $request)
    {
        return $this->render('default/editor.html.twig');
    }

//    public function convocantesAction(Request $request)
//    {
//        $datatableParams = $request->query->all();
//        $queryParams = $this->getParams($request);
//
//        /***
//         * @var Client
//         */
//        $client = $this->get('csa_guzzle.client.api');
//
//        /***
//         * @var ResponseInterface
//         */
//        $response = $client->get(
//            '/dncpws/publico/parametros/convocantes',
//            array(
//                'query' => $queryParams,
//            )
//        );
//
//        $serviceResponse = json_decode($response->getBody(), true);
//
//        $totalItems = $this->getPaginationTotalItems($serviceResponse);
//
//        $data = $this->dummyAddRowId($serviceResponse['list']);
//        $data = $this->dummyActions($data);
////        $data = $serviceResponse['list'];
//
//        $ret = array(
//            'draw' => $datatableParams['draw'],
//            'recordsTotal' => $totalItems,
//            'recordsFiltered' => $totalItems,
//            'data' => $data,
//        );
//
////        if ($request->query->get('download')) {
////            return $this->downloadCsv($ret['data']);
////        }
//
//        if ($request->query->get('debug')) {
//            dump($datatableParams);
//            dump($queryParams);
//            dump($serviceResponse);
//            dump($ret);
//
//            return new Response('');
//        }
//
//        return new JsonResponse($ret);
//    }

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

        $totalItems = $this->getPaginationTotalItems($serviceResponse);

        $data = $this->dummyAddRowId($serviceResponse['list']);

        if ($request->query->get('download')) {
            return $this->downloadCsv($data);
        }

        $data = $this->dummyActions($data);
//        $data = $serviceResponse['list'];

        $ret = array(
            'draw' => $datatableParams['draw'],
            'recordsTotal' => $totalItems,
            'recordsFiltered' => $totalItems,
            'data' => $data,
        );

        if ($request->query->get('debug')) {
            dump($datatableParams);
            dump($queryParams);
            dump($serviceResponse);
            dump($ret);

            return new Response('');
        }

        return new JsonResponse($ret);
    }

    public function downloadCsv(array $data, $reportName = 'reporte')
    {
        $response = new StreamedResponse();

        $contentDisposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $reportName.'.csv'
        );

        $response->headers->set('Content-Disposition', $contentDisposition);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');

        $response->setCallback(
            function () use ($data) {
                $exporter = $this->get('intriro_csv.exporter.default');
                $exporter->export('php://output', $data);
            }
        );

        $response->send();

        return $response;
    }

    public function editarAction(Request $request)
    {
        $this->get('logger')->info(print_r($request->request->all(), true));

        $ret = array(
            'data' => array(
                "slug" => "administracion-nacional-electricidad",
                "codigo" => 167,
                "nombre_simple" => "Administración Nacional de Electricidad (ANDE) aaaaaaaaaaaaaaaa",
                "nombre" => "Administración Nacional de Electricidad (ANDE)",
                "tipo" => "Entidad",
                "dependiente_de" => null,
                "dependiente_de_codigo" => null,
                "nivel_entidad" => "Empresas Públicas",
                "tipo_entidad" => "Entidades Descentralizadas",
                "DT_RowId" => 167,
            ),
        );

        return new JsonResponse($ret);
    }

    private function dummyAddRowId($data)
    {
        for ($i = 0; $i < count($data); ++$i) {
            $data[$i]['DT_RowId'] = $data[$i]['codigo'];
        }

        return $data;
    }

    private function dummyActions($data)
    {
        $actions = array(
            array(
                'planificacion' => array('text' => 'Ver Planificación', 'url' => '#')
            ),
            array(
                'planificacion' => array('text' => 'Ver Planificación', 'url' => '#'),
                'convocatoria' => array('text' => 'Ver Convocatoria', 'url' => '#'),
            ),
            array(
                'planificacion' => array('text' => 'Ver Planificación', 'url' => '#'),
                'convocatoria' => array('text' => 'Ver Convocatoria', 'url' => '#'),
                'adjudicación' => array('text' => 'Ver Adjudicación', 'url' => '#'),
            ),
            array(
                'convocatoria' => array('text' => 'Ver Convocatoria', 'url' => '#'),
                'adjudicación' => array('text' => 'Ver Adjudicación', 'url' => '#'),
            ),
        );

        for ($i = 0; $i < count($data); ++$i) {
            $data[$i]['actions'] = $actions[array_rand($actions)];
        }

        return $data;
    }

    private function getPaginationTotalItems($serviceResponse)
    {
        if (isset($serviceResponse['pagination'])) {
            return $serviceResponse['pagination']['total_items'];
        } else {
            return count($serviceResponse['list']);
        }
    }

    private function getParams(Request $request)
    {
        $datatableParams = $request->query->all();

        $ret = array(
            'offset' => $datatableParams['start'],
            'limit' => $datatableParams['length'],
            'order' => $this->getOrder($datatableParams),
        );

        if ($filters = $this->getFilters($datatableParams)) {
            $ret = array_merge($ret, $filters);
        }

        if(isset($datatableParams['download'])){
            unset($ret['offset']);
            unset($ret['limit']);
            $ret['show_pagination'] = false;
//            $ret['formato_csv'] = 'true';
        }

        return $ret;
    }

    private function getOrder($datatableParams)
    {
        $ret = array();

        foreach ($datatableParams['order'] as $order) {
            if ($datatableParams['columns'][$order['column']]['orderable']) {
                $i = $order['column'];

                $ret[] = $datatableParams['columns'][$i]['data'].' '.$order['dir'];
            }
        }

        return implode($ret, ',');
    }

    private function getFilters($datatableParams)
    {
        $ret = array();

        foreach ($datatableParams['columns'] as $column) {
            if ($column['searchable'] && $column['search']['value']) {
                $ret[$column['data']] = $column['search']['value'];
            }
        }

        if ($datatableParams['search']['value']) {
            $ret['search'] = $datatableParams['search']['value'];
        }

        return $ret;
    }
}
