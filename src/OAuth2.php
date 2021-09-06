<?php

namespace Intellischool;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use kamermans\OAuth2\Exception\OAuth2Exception;
use kamermans\OAuth2\GrantType\AuthorizationCode;
use kamermans\OAuth2\OAuth2Middleware;
use kamermans\OAuth2\Token\Serializable;
use kamermans\OAuth2\Token\TokenInterface;

class OAuth2
{
    const TOKEN_EXCHANGE_URL = 'https://core.intellischool.net/connect/token';
    const REFRESH_TOKEN_URL = self::TOKEN_EXCHANGE_URL;

    /**
     * See https://docs.intellischool.co/read/api/auth/oauth2 for more details. Redirect the user to the returned URL
     *
     * @param string      $clientId    Your Client Id
     * @param string      $redirectUri The Redirect URI configured for your application
     * @param string[]    $scopes      The permissions to request
     * @param string|null $state       Optional state tied to the user session to verify responses with
     *
     * @return string
     */
    public static function getAuthUrl(string $clientId, string $redirectUri, array $scopes = ['offline_access'], ?string $state = null): string
    {
        $query = [
            'response_type' => 'code',
            'client_id'     => $clientId,
            'scope'         => implode(' ', $scopes),
            'redirect_uri'  => $redirectUri
        ];
        if (!empty($state))
        {
            $query['state'] = $state;
        }
        return 'https://core.intellischool.net/connect/authorize?' . http_build_query($query);
    }

    /**
     * Exchange an OAuth2 Authorization Code for a token. You should verify any passed state parameter before calling this function.
     *
     * @param string                  $code         The code received from the callback, usually $_GET['code']
     * @param string                  $redirectUri  The Redirect URI configured for your application
     * @param string                  $clientId     Your Client Id
     * @param string                  $clientSecret Your Client Secret
     *
     * @return array An associative array containing at least the keys 'access_token', 'refresh_token', 'expires_at'
     */
    public static function exchangeCodeForToken(string $code, string $redirectUri, string $clientId, string $clientSecret): array
    {
        $httpClient = new Client([
                                     'base_uri' => self::TOKEN_EXCHANGE_URL,
                                 ]);

        $grant_type = new AuthorizationCode($httpClient, [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code
        ]);
        $oauth = new OAuth2Middleware($grant_type);

        try
        {
            if ($oauth->getAccessToken() == null)
            {
                throw new IntelliSchoolException('No access token granted');
            }
        }
        catch (OAuth2Exception $e)
        {
            throw new IntelliSchoolException('No access token granted due to error', 0, $e);
        }
        $rawToken = $oauth->getRawToken();
        if (empty($rawToken))
        {
            throw new IntelliSchoolException('No access token granted: library error');
        }
        if (!($rawToken instanceof Serializable)) {
            throw new IntelliSchoolException('Returned token doesn\'t implement Serializable');
        }
        return $rawToken->serialize();
    }

}