<?php

require 'JwtHandler.php';

try {
    if (isset($_POST['uuid'])) {
        $jwt = new JwtHandler();

        $token = $jwt->_jwt_encode_data(
            $_SERVER['HTTP_ORIGIN'],
            [
                "uuid" => $_POST['uuid']
            ]
        );

        echo json_encode([
            'status' => true,
            'messege' => 'Token Generate successfully.',
            'data' => [
                // "endpoint"=> "sync.vic.intellischool.com.au",
                // "tenant"=> "34eacce3-9624-48da-ba8e-9e43fdb275af",
                // 'deployment' => '527b2a7c-b296-4397-a62f-1432c4a870f1',
                'token' => $token
            ]
        ]);
    }
} catch (\Exception $e) {
    echo 'Caught: Exception - ' . $e->getMessage();
}
