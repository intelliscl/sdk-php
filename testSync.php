<?php
require_once 'vendor/autoload.php';

$handler = \Intellischool\SyncHandler::createWithIdAndSecret($_GET['deploymentId'], $_GET['deploymentSecret']);
try
{
    $handler->doSync();
} catch (Exception $e) {
    var_dump($e);
}