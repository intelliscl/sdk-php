<?php

require 'JwtHandler.php';

if (isset($_POST['token'])) {

    $jwt = new JwtHandler();

    try {
        $data =  $jwt->_jwt_decode_data(trim($_POST['token']));
        print_r($data);
        echo "<br><hr>";
    } catch (\Exception $e) {
        echo 'Caught: Exception - ' . $e->getMessage();
    }
}
?>

<form action="" method="POST">
    <label for="_token"><strong>Enter Token</strong></label>
    <input type="text" name="token" id="_token">
    <input type="submit" value="Docode">
</form>