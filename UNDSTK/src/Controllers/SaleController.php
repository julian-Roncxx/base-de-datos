<?php

namespace Controllers;

use Models\Sale;

class SaleController
{
    private $model;


    public function __construct(
        Sale $model
    ) {

        $this->model = $model;
    }


    private function sendJson(
        $data,
        $statusCode = 200
    ) {

        http_response_code(
            $statusCode
        );

        header(
            'Content-Type: application/json'
        );

        echo json_encode($data);

        exit();
    }


    public function store()
    {

        $data =
            json_decode(
                file_get_contents(
                    "php://input"
                ),
                true
            );


        if (
            !isset($data['total']) ||
            !isset($data['items']) ||
            empty($data['items'])
        ) {

            $this->sendJson(
                [
                    'status' =>
                        'ERROR',

                    'message' =>
                        'Datos de venta incompletos'
                ],
                400
            );
        }


        $resultado =
            $this->model
            ->createSale(
                $data['total'],
                $data['items']
            );


        if ($resultado === true) {

            $this->sendJson([
                'status' =>
                    'SUCCESS',

                'message' =>
                    'Venta procesada con COMMIT'
            ]);

        }


        $this->sendJson(
            [
                'status' =>
                    'ERROR',

                'message' =>
                    $resultado,

                'transaction' =>
                    'ROLLBACK'
            ],
            409
        );
    }
}