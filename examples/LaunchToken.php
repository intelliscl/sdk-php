<?php
require_once '../vendor/autoload.php';

$token = (new \Intellischool\LTI\LaunchToken())->setIssuer('https://lms.school.edu')
                                               ->setDeploymentId('test_deployment_id')
                                               ->setSubject('jane@school.edu')
                                               ->setName('Ms Jane Marie Doe')
                                               ->setGivenName('Jane')
                                               ->setMiddleName('Marie')
                                               ->setFamilyName('Doe')
                                               ->setEmail('jane@school.edu')
                                               ->setPicture('https://lms.school.edu/jane.jpg')
                                               ->setRole('student')
                                               ->setTargetLinkUri('https://analytics.intellischool.cloud/dashboard/12345')
                                               ->setResourceLink('http://some.url')
                                               ->setLaunchPresentation('iframe');
$key = file_get_contents('private_key.pem');

echo $token->build($key);