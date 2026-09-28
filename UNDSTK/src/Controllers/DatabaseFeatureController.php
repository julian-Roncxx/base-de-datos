<?php

namespace Controllers;

use Models\DatabaseFeature;
use Throwable;

class DatabaseFeatureController
{
    private $model;


    public function __construct(
        DatabaseFeature $model
    ) {

        $this->model = $model;
    }


    public function index()
    {

        try {

            $datos =
                $this->model
                ->getDashboard();


            http_response_code(200);

            header(
                'Content-Type: application/json'
            );


            echo json_encode([

                'status' =>
                    'SUCCESS',

                'data' =>
                    $datos

            ]);


        } catch (Throwable $e) {


            http_response_code(500);

            header(
                'Content-Type: application/json'
            );


            echo json_encode([

                'status' =>
                    'ERROR',

                'message' =>
                    'No se pudo consultar la información de la base de datos.'

            ]);
        }
    }
}