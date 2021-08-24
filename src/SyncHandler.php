<?php

namespace Intellischool;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Intellischool\Model\JobStatus;
use Intellischool\Model\SyncJob;

class SyncHandler
{
    public const AUTH_ENDPOINT = 'https://core.intellischool.net/auth/onprem-sync';
    public const SYNC_AGENT_NAME = 'Intellischool PHP Sync Agent';
    public const SYNC_AGENT_VERSION = "0.0.1";
    public const AUTH_SERVICE_NAME = 'Auth Service';
    public const JOB_DISPATCH_NAME = 'Job Dispatch Queue';
    public const JOB_MANAGER_NAME = 'Job Manager';
    public const SQL_SERVICE_NAME = 'SQL Sync Service';
    public const SUPPORT_EMAIL = 'help@intellischool.co';
    public const SUPPORT_WEBSITE = 'https://help.intellischool.co';

    /**
     * @var int Timeout value (in seconds) for SQL queries. Defaults to 7200.
     */
    private int $sqlTimeout = 7200;

    /**
     * @var \Intellischool\IDaPAuthHandler Auth handler to use
     */
    private IDaPAuthHandler $authHandler;

    /**
     * @var \GuzzleHttp\Client HTTP client to use to make requests
     */
    private Client $httpClient;

    private function __construct(IDaPAuthHandler $authHandler)
    {
        $this->httpClient = new Client([RequestOptions::HTTP_ERRORS => false]);
        $this->authHandler = $authHandler;
    }

    public static function createWithIdAndSecret($deploymentId, $deploymentSecret): self
    {
        return new self(new BasicIdapHandler($deploymentId, $deploymentSecret));
    }

    public static function createWithOAuth2($tokenStore): self
    {
        throw new \Exception("Not yet implemented");//todo OAuth2
    }

    /**
     * @return \string[][]
     */
    private function getSyncGuzzleOptions(): array
    {
        return [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $this->authHandler->getSyncToken()
            ]
        ];
    }

    private function getSyncUrl($template): string
    {
        return 'https://'.$this->authHandler->getSyncEndpoint().str_replace(
            ['{tenant_id}', '{deployment_id}'],
            [$this->authHandler->getTenantId(), $this->authHandler->getDeploymentId()],
            $template
        );
    }

    /**
     * @return \Intellischool\Model\SyncJob[]
     */
    private function getSyncJobs(): array
    {
        try
        {
            $response = $this->httpClient->get($this->getSyncUrl('/poll/{tenant_id}/{deployment_id}?v=' . self::SYNC_AGENT_VERSION),
                                               $this->getSyncGuzzleOptions()
            );
        }
        catch (GuzzleException $e)
        {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setSource(self::JOB_DISPATCH_NAME)
                    ->setEventType('Error')
                    ->setEventId(2000)
                    ->setMessage('Sync Agent was unable to retrieve sync jobs from the IDaP. ' . $e->getMessage())
            );
            throw new IntelliSchoolException('Failed to get jobs list', $e);
        }
        if ($response->getStatusCode() != 200) {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setSource(self::JOB_DISPATCH_NAME)
                    ->setEventType('Error')
                    ->setEventId(2000+$response->getStatusCode())
                    ->setMessage('Sync Agent was unable to retrieve sync jobs from the IDaP.')
            );
            throw new IntelliSchoolException('Failed to get jobs list. HTTP Response code '.$response->getStatusCode().' Body: '.$response->getBody()->getContents());
        }
        $body = json_decode($response->getBody()->getContents());
        if (empty($body->sync_jobs)) {
            return [];
        }
        return array_map(function($element){
            return new SyncJob($element);
        }, $body->sync_jobs);
    }

    private function updateJobStatus(JobStatus $status)
    {
        for ($tries = 0; $tries < 5; $tries++)
        {
            try
            {
                $response = $this->httpClient->patch(
                    $this->getSyncUrl('/job/{tenant_id}/{deployment_id}' . !empty($status->jobInstance) ? '/' . $status->jobInstance : ''),
                    $this->getSyncGuzzleOptions()
                );
                if (!empty($response) && $response->getStatusCode() == 200) {
                    return;
                } else {
                    user_error("Attempt $tries failed to update job status: ".$response->getStatusCode().': '.$response->getBody()->getContents(), E_USER_WARNING);
                }
            }
            catch (GuzzleException $e)
            {
                user_error("Attempt $tries failed to update job status: ".$e, E_USER_WARNING);
            }
        }
        user_error('Failed to update job status after 5 attempts', E_USER_WARNING);
    }

    private function uploadSyncJob()
    {
        //todo
    }

    public function doSync()
    {
        $this->authHandler->authorise($this->httpClient);
        $jobs = $this->getSyncJobs();
        if (empty($jobs)) {
            return;
        }
        foreach ($jobs as $syncJob)
        {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setEventType('Info')
                    ->setSource(self::JOB_DISPATCH_NAME)
                    ->setEventId(2000)
                    ->setMessage('Sync Agent successfully retrieved job.')
                    ->setJobInstance($syncJob->instanceId)
            );
            echo $syncJob->getPdoString($this->sqlTimeout)."\n";
        }
        //todo
    }

    /**
     * @return int Timeout value (in seconds) for SQL queries. Defaults to 7200.
     */
    public function getSqlTimeout(): int
    {
        return $this->sqlTimeout;
    }

    /**
     * Set a new timeout value for SQL queries
     *
     * @param int $sqlTimeout Timeout value (in seconds)
     *
     * @return self
     */
    public function setSqlTimeout(int $sqlTimeout): self
    {
        $this->sqlTimeout = $sqlTimeout;
        return $this;
    }
}