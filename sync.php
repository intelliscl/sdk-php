<?php
require "vendor/autoload.php";
require 'SyncHandler.php';

try {
    $sync = new SyncHandler();
    $sync->iscSyncAuth();
} catch (\Exception $e) {
    echo 'Caught: Exception - ' . $e->getMessage();
}
