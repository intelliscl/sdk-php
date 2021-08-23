<?php

namespace Intellischool;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class BasicIdapHandler extends IDaPAuthHandler
{

    private string $deploymentId;
    private string $deploymentSecret;

    public function __construct(string $deploymentId, string $deploymentSecret)
    {
        $this->deploymentId = $deploymentId;
        $this->deploymentSecret = $deploymentSecret;
    }

    /**
     * @inheritDoc
     */
    function getDeploymentId(): string
    {
        return $this->deploymentId;
    }


    /**
     * @inheritDoc
     */
    public function authorise(Client $httpClient)
    {
        try
        {
            $response = $httpClient->post(SyncHandler::AUTH_ENDPOINT, [RequestOptions::AUTH => [$this->deploymentId, $this->deploymentSecret, 'basic']]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to authorise at IDap, HTTP client error", 0, $e);//todo exceptions
        }
        $this->handleAuthResponse($response);
    }
}