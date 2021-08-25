<?php

namespace Intellischool;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Intellischool\Model\JobStatus;
use Intellischool\Model\SyncJob;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SyncHandler implements LoggerAwareInterface
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

    /**
     * @var \Psr\Log\LoggerInterface Logger to use. Defaults to a no-op logger
     */
    private LoggerInterface $logger;

    private function __construct(IDaPAuthHandler $authHandler)
    {
        $this->logger = new NullLogger();
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
        $url = 'https://' . $this->authHandler->getSyncEndpoint() . str_replace(
                ['{tenant_id}', '{deployment_id}'],
                [$this->authHandler->getTenantId(), $this->authHandler->getDeploymentId()],
                $template
            );
        $this->logger->debug("Generated url: ".$url, ['template'=>$template]);
        return $url;
    }

    private function logGuzzleResponse(ResponseInterface $response)
    {
        $this->logger->debug('Response '.$response->getStatusCode(), ['headers'=>$response->getHeaders(), 'body'=>(string)$response->getBody()]);
        $response->getBody()->rewind();
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
                    ->setEventType(JobStatus::ERROR_EVENT)
                    ->setEventId(2000)
                    ->setMessage('Sync Agent was unable to retrieve sync jobs from the IDaP. ' . $e->getMessage())
            );
            throw new IntelliSchoolException('Failed to get jobs list', $e);
        }
        $this->logGuzzleResponse($response);
        if ($response->getStatusCode() != 200) {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setSource(self::JOB_DISPATCH_NAME)
                    ->setEventType(JobStatus::ERROR_EVENT)
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
        $statusUpdateUrl = $this->getSyncUrl('/job/{tenant_id}/{deployment_id}' . (!empty($status->jobInstance) ? '/' . $status->jobInstance : ''));
        $this->logger->debug('Updating job status', ['status'=>$status, 'url'=>$statusUpdateUrl]);
        for ($tries = 0; $tries < 5; $tries++)
        {
            try
            {
                $options = $this->getSyncGuzzleOptions();
                $options[RequestOptions::HEADERS]['Content-Type'] = 'application/json';
                $options[RequestOptions::BODY] = json_encode($status);
                $response = $this->httpClient->patch(
                    $statusUpdateUrl,
                    $options
                );
                $this->logGuzzleResponse($response);
                if (!empty($response) && $response->getStatusCode() == 200) {
                    return;
                } else {
                    $this->logger->warning("Attempt $tries failed to update job status: ".$response->getStatusCode(), ['body'=>$response->getBody()->getContents()]);
                }
            }
            catch (GuzzleException $e)
            {
                $this->logger->warning("Attempt $tries failed to update job status", ['exception'=>$e]);
            }
        }
        $this->logger->error('Failed to update job status after 5 attempts');
    }

    private function processJob(SyncJob $syncJob)
    {
        $this->logger->debug("Processing job", ['job'=>$syncJob]);
        $this->updateJobStatus(
            (new JobStatus())
                ->setEventType(JobStatus::INFO_EVENT)
                ->setSource(self::JOB_DISPATCH_NAME)
                ->setEventId(2000)
                ->setMessage('Sync Agent successfully retrieved job.')
                ->setJobInstance($syncJob->instanceId)
        );
        $pdoString = $syncJob->getPdoString($this->sqlTimeout);
        $query = $syncJob->query;//don't inline this, empty() wont work on the dynamic prop
        $this->logger->debug('Job Info', ['pdoString' => $pdoString, 'query' => $query]);
        if (empty($pdoString) || empty($query))
        {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setEventType(JobStatus::ERROR_EVENT)
                    ->setSource(self::SQL_SERVICE_NAME)
                    ->setEventId(3602)
                    ->setMessage("An error was encountered while extracting data: \nJob is missing info needed for database query")
                    ->setJobInstance($syncJob->instanceId)
                    ->setJobStatus("failed")
            );
            return;
        }
        try
        {
            $pdo = new \PDO($pdoString, $syncJob->dbUsername, $syncJob->dbPassword);
            $file = tmpfile();
            if ($file === false) {
                $this->logger->error('Could not create temp file');
                $this->updateJobStatus(
                    (new JobStatus())
                        ->setEventType(JobStatus::ERROR_EVENT)
                        ->setSource(self::SQL_SERVICE_NAME)
                        ->setEventId(3602)
                        ->setMessage("An error was encountered while extracting data: \nCould not create temp file")
                        ->setJobInstance($syncJob->instanceId)
                        ->setJobStatus("failed")
                );
                return;
            }
            $statement = $pdo->query($query);
            $cols = null;//ensure they always appear in the same order
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                if ($cols == null) {
                    $cols = array_keys($row);
                    fputcsv($file, $cols);
                }
                $values = array();
                foreach ($cols as $col)
                {
                    $values[$col] = $row[$col];
                }
                fputcsv($file, $values);
            }
            $this->updateJobStatus(
                (new JobStatus())
                    ->setEventType(JobStatus::INFO_EVENT)
                    ->setSource(self::SQL_SERVICE_NAME)
                    ->setEventId(3002)
                    ->setMessage("Successfully executed SQL and saved output.")
                    ->setJobInstance($syncJob->instanceId)
                    ->setMeta(['rows'=>$statement->rowCount()])
            );
        } catch (\PDOException $e) {
            $this->logger->error('Sync process failed failed', ['exception'=>$e]);
            $this->updateJobStatus(
                (new JobStatus())
                    ->setEventType(JobStatus::ERROR_EVENT)
                    ->setSource(self::SQL_SERVICE_NAME)
                    ->setEventId(3602)
                    ->setMessage("An error was encountered while extracting data: \nPDOException: ".$e->getMessage())
                    ->setJobInstance($syncJob->instanceId)
                    ->setJobStatus("failed")
                    ->setMeta($e->getTrace())
            );
            return;
        }
        fflush($file);
        rewind($file);

        $this->uploadSyncJob($syncJob, $file);
    }

    /**
     * @param \Intellischool\Model\SyncJob $syncJob
     * @param resource                     $tmpFile
     */
    private function uploadSyncJob(SyncJob $syncJob, $tmpFile)
    {
        try
        {
            $response = $this->httpClient->get($this->getSyncUrl('/upload/{tenant_id}/{deployment_id}/' . $syncJob->instanceId),
                                               $this->getSyncGuzzleOptions()
            );
        }
        catch (GuzzleException $e)
        {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setSource(self::JOB_MANAGER_NAME)
                    ->setEventType(JobStatus::ERROR_EVENT)
                    ->setEventId(2000)
                    ->setMessage('Sync agent encountered an error while retrieving upload URI: ' . $e->getMessage())
                    ->setJobStatus('failed')
                    ->setJobInstance($syncJob->instanceId)
            );
            throw new IntelliSchoolException('Failed to get job upload URI.', $e);
        }
        $this->logGuzzleResponse($response);
        if ($response->getStatusCode() != 200) {
            $this->updateJobStatus(
                (new JobStatus())
                    ->setSource(self::JOB_MANAGER_NAME)
                    ->setEventType(JobStatus::ERROR_EVENT)
                    ->setEventId(2000+$response->getStatusCode())
                    ->setMessage('Sync agent encountered an error while retrieving upload URI.')
                    ->setJobStatus('failed')
                    ->setJobInstance($syncJob->instanceId)
            );
            throw new IntelliSchoolException('Failed to get job upload URI. HTTP Response code '.$response->getStatusCode().' Body: '.$response->getBody()->getContents());
        }
        $this->updateJobStatus(
            (new JobStatus())
                ->setSource(self::JOB_MANAGER_NAME)
                ->setEventType(JobStatus::INFO_EVENT)
                ->setEventId(2002)
                ->setMessage('Retrieved upload URI. Commencing upload.')
                ->setJobStatus('uploading')
                ->setJobInstance($syncJob->instanceId)
        );
    }

    public function doSync()
    {
        $this->authHandler->authorise($this->httpClient);
        $jobs = $this->getSyncJobs();
        if (empty($jobs)) {
            $this->logger->info("No jobs found");
            return;
        }
        $this->logger->info("Found ".count($jobs).' jobs');
        foreach ($jobs as $syncJob)
        {
            $this->processJob($syncJob);
        }
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

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}