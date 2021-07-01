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
                'token' => $token
            ]
        ]);
    }
} catch (\Exception $e) {
    echo 'Caught: Exception - ' . $e->getMessage();
}
