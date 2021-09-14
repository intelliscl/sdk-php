<?php

namespace Intellischool;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use kamermans\OAuth2\Exception\OAuth2Exception;
use kamermans\OAuth2\GrantType\NullGrantType;
use kamermans\OAuth2\GrantType\RefreshToken;
use kamermans\OAuth2\OAuth2Middleware;
use kamermans\OAuth2\Persistence\TokenPersistenceInterface;
use kamermans\OAuth2\Token\RawToken;
use kamermans\OAuth2\Token\Serializable;
use kamermans\OAuth2\Token\TokenInterface;

//todo token update listener
class OAuth2IdapHandler extends IDaPAuthHandler implements TokenPersistenceInterface
{

    private array $tokenStore;
    private string $clientId;
    private string $clientSecret;

    public function __construct($tokenStore, $clientId, $clientSecret)
    {
        $this->tokenStore = $tokenStore;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @inheritDoc
     */
    public function authorise(Client $httpClient)
    {
        // Authorization client - this is used to request OAuth access tokens
        $reauth_client = new Client([
                                        // URL for access_token request
                                        'base_uri' => OAuth2::REFRESH_TOKEN_URL,
                                    ]);
        $reauth_config = [
            "client_id"     => $this->clientId,
            "client_secret" => $this->clientSecret
        ];
        $oauth = new OAuth2Middleware(new NullGrantType(), new RefreshToken($reauth_client, $reauth_config));
        $oauth->setTokenPersistence($this);

        $stack = HandlerStack::create();
        $stack->push($oauth);

        // This is the normal Guzzle client that you use in your application
        $authClient = new Client([
                                     'handler' => $stack,
                                     'auth'    => 'oauth',
                                 ]);
        try
        {
            $response = $authClient->post(SyncHandler::AUTH_ENDPOINT, [RequestOptions::AUTH => ['oauth']]);
        }
        catch (GuzzleException $e)
        {
            throw new IntelliSchoolException("Failed to authorise at IDap, HTTP client error", 0, $e);
        }
        catch (OAuth2Exception $e)
        {
            throw new IntelliSchoolException("Failed to authorise at IDap, OAuth2 error", 0, $e);
        }
        $this->handleAuthResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function getDeploymentId(): string
    {
        return $this->clientId;
    }

    /**
     * @inheritDoc
     */
    public function restoreToken(TokenInterface $token)
    {
        if (!($token instanceof Serializable)) {
            $token = new RawToken();
        }
        return $token->unserialize($this->tokenStore);
    }

    /**
     * @inheritDoc
     */
    public function saveToken(TokenInterface $token)
    {
        $this->tokenStore = $token->serialize();
    }

    /**
     * @inheritDoc
     */
    public function deleteToken()
    {
        // TODO: Implement deleteToken() method.
    }

    /**
     * @inheritDoc
     */
    public function hasToken(): bool
    {
        return true;
    }


}