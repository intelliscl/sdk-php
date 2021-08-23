<?php

namespace Intellischool;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

abstract class IDaPAuthHandler
{
    protected ?\stdClass $authResponse = null;

    private function requireAuthCalled(){
        if (empty($this->authResponse)) {
            throw new \RuntimeException("IDaPAuthHandler->authorise not yet called");
        }
    }

    protected function handleAuthResponse(ResponseInterface $response)
    {
        if ($response->getStatusCode() == 200) {
            $this->authResponse = json_decode($response->getBody()->getContents());
            //todo validate?
        } else {
            throw new \RuntimeException("Failed to authorise at IDap, non-200 response code: ".$response->getStatusCode());//todo exceptions
        }
    }

    /**
     * @return mixed Perform authentication with IDaP to retrieve values.
     */
    public abstract function authorise(Client $httpClient);

    /**
     * Get the deployment_id or OAuth2 ClientId
     *
     * @return string
     */
    public abstract function getDeploymentId(): string;

    /**
     * Get the Tenant Id return from auth. Must have called authorise first.
     *
     * @return string
     */
    public function getTenantId(): string
    {
        $this->requireAuthCalled();
        return $this->authResponse->tenant;
    }

    /**
     * Get the Sync Endpoint returned from auth. Must have called authorise first.
     *
     * @return string
     */
    public function getSyncEndpoint(): string
    {
        $this->requireAuthCalled();
        return $this->authResponse->endpoint;
    }

    /**
     * Get the Sync Token returned from auth. Must have called authorise first.
     *
     * @return string
     */
    public function getSyncToken(): string
    {
        $this->requireAuthCalled();
        return $this->authResponse->token;
    }
}