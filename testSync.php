<?php
require_once 'vendor/autoload.php';

class PrintLogger extends \Psr\Log\AbstractLogger{

    public function log($level, $message, array $context = array())
    {
        echo "$level: $message\n";

        if (!empty($context)) {
            var_dump($context);
        }

        echo "\n-------------------------------\n\n";
    }
}

$handler = \Intellischool\SyncHandler::createWithIdAndSecret($argv[1], $argv[2]);
$handler->setLogger(new PrintLogger());
try
{
    $handler->doSync();
} catch (Exception $e) {
	echo "Unhandled exception: ";
    var_dump($e);
}