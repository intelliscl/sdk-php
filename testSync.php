<?php
require_once 'vendor/autoload.php';

class PrintLogger extends \Psr\Log\AbstractLogger{

    public function log($level, $message, array $context = array())
    {
        echo "<div><strong>$level</strong>: $message<br>Context:<pre>";
        var_dump($context);
        echo "</pre></div>";
    }
}

$handler = \Intellischool\SyncHandler::createWithIdAndSecret($_GET['deploymentId'], $_GET['deploymentSecret']);
$handler->setLogger(new PrintLogger());
try
{
    $handler->doSync();
} catch (Exception $e) {
	echo "<b>Unhandled exception</b>: ";
    var_dump($e);
}