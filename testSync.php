<?php
require_once 'vendor/autoload.php';

class PrintLogger extends \Psr\Log\AbstractLogger{

    public function log($level, $message, array $context = array())
    {
        echo "<div><strong>$level</strong>: $message";

        if (!empty($context)) {
            echo "<br>Context:<textarea rows='10' cols='100'>";
            var_dump($context);
            echo "</textarea>";
        }

        echo "</div>";
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