<?php
/**
 * Run this script from the command line, with your deployment id and deployment secret as arguments, e.g.
 *  php Sync.php my_deployment_id my_deployment_secret
 */
use Psr\Log\LogLevel;

require_once '../vendor/autoload.php';

class PrintLogger extends \Psr\Log\AbstractLogger{
    private bool $enableDebugLog = false;

    public function __construct(bool $debug)
    {
        $this->enableDebugLog = $debug;
    }

    public function log($level, $message, array $context = array())
    {
        echo "$level: $message\n";

        if (!empty($context)) {
            var_dump($context);
        }

        echo "\n-------------------------------\n\n";
    }

    public function debug($message, array $context = array())
    {
        if ($this->enableDebugLog) {
            $this->log(LogLevel::DEBUG, $message, $context);
        }
    }


}

$handler = \Intellischool\SyncHandler::createWithIdAndSecret($argv[1], $argv[2]);
$handler->setLogger(new PrintLogger(false));//logger is optional
try
{
    $handler->doSync();
} catch (Exception $e) {
	echo "Unhandled exception: ";
    var_dump($e);
}