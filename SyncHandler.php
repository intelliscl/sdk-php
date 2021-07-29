<?php

// require 'autoload.php';
require_once "vendor/autoload.php";

use \Curl\Curl;
use \Dotenv\Dotenv;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;
use MicrosoftAzure\Storage\Common\Models\Logging;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;

class SyncHandler
{
    //Global Constants
    const AUTH_ENDPOINT = "https://core.intellischool.net/auth/onprem-sync";
    const SYNC_AGENT_NAME = "Intellischool Sync Agent";
    const SYNC_AGENT_VERSION = 1.0;
    const AUTH_SERVICE_NAME = "Auth Service";
    const JOB_DISPATCH_NAME = "Job Dispatch Queue";
    const JOB_MANAGER_NAME = "Job Manager";
    const SQL_SYNC_SERVICE = "SQL Sync Service";
    const SUPPORT_EMAIL = "help@intellischool.co";
    const SUPPORT_WEBSITE = "https://help.intellischool.com";

    protected $deploymentId;
    protected $deploymentSecret;
    protected $tempFolder;
    protected $sqlTimeout;
    protected $authType;
    protected $testAzure;

    public function __construct()
    {
        // Load .env configaration with safe loader. 
        // Please make sure TIMEZONE, DEPLOYMENT_ID, DEPLOYMENT_SECRET is exist in .env at root folder
        $dotenv = Dotenv::createMutable(__DIR__);
        $dotenv->safeLoad();
        $dotenv->required('TIMEZONE')->notEmpty();
        $dotenv->required('DEPLOYMENT_ID')->notEmpty();
        $dotenv->required('DEPLOYMENT_SECRET')->notEmpty();

        // Set your default time-zone
        date_default_timezone_set($_ENV['TIMEZONE']);

        //Instance Constants
        $this->authStartdAt = time();
        $this->deploymentId = $_ENV['DEPLOYMENT_ID'];
        $this->deploymentSecret = $_ENV['DEPLOYMENT_SECRET'];
        $this->tempFolder = "sync-csv" . DIRECTORY_SEPARATOR;
        $this->sqlTimeout = 7200;

        $this->authType = 'basic';
        $this->extFile = '.csv';


        $this->testAzure = false;
        if ($this->testAzure) {

            $jobInstance = 'e8cc3cdf-89a1-5afa-80cf-b9671fe29028';

            $sasPath = 'https://issyncauvic.blob.core.windows.net/ingestion/34eacce3-9624-48da-ba8e-9e43fdb275af_e9729394-7b9d-bdbd-d320-2d258c6682b8.csv?st=2021-07-29T14%3A09%3A20Z&se=2021-07-29T14%3A39%3A20Z&sp=w&sv=2018-03-28&sr=b&sig=4YsRE0uelP71DzYjLt7%2BEpOULt9jsJlpUTQPXj2ExVk%3D403';
            $sasUrl = parse_url($sasPath);
            print_r($sasUrl);

            parse_str($sasUrl['query'], $outPut);

            print_r($outPut);
            // exit;

            $blobEndpoint = $sasUrl['scheme'] . '://' . $sasUrl['host'] . $sasUrl['path'];
            // $signeture = '?' . $sasUrl['query'];
            $signeture = $outPut['sig'];
            $containerName = explode('/', $sasUrl['path'])[1];

            // $azureConString = "BlobEndpoint=$blobEndpoint;SharedAccessSignature=$signeture";
            $azureConString = "BlobEndpoint=$blobEndpoint;SharedAccessSignature=$signeture";

            // Create blob client.
            $blobRestProxy = BlobRestProxy::createBlobService($azureConString);
            // print_r($blobRestProxy);exit;

            $csvFilePath = $this->tempFolder . $jobInstance . $this->extFile;

            $csvContent = fopen($csvFilePath, 'r');

            $blobOptions = new CreateBlockBlobOptions();
            $blobOptions->setMetadata([
                'version' => $outPut['sv'],
                // 'Content-Length' => filesize($csvFilePath),
                'date' => date('Y-m-d'),
                // 'cache-control' => 'public, max-age=259200',
                // 'Authorization' => '',
            ]);
            // print_r($options);
            // exit;

            //Upload blob
            $blobRestProxy->createBlockBlob($containerName, $jobInstance . $this->extFile, $csvContent, $blobOptions);

            print_r($blobRestProxy);
            exit;
        }
    }

    public function iscSyncAuth()
    {
        // dummy call This should be removed end of the development.
        $curlD = new Curl();
        $curlD->get('https://is-onprem-sync-au-vic.azurewebsites.net/reset?code=OW1ODVfMAaTd5EX3iN0CwiSWYeqS5sOobk8MuSiXmhmDjD174QjbRQ==');

        // Define Vars
        $errorResponse = NULL;

        try {
            /**
             * Check tempFolder is exist if not then throw ar error
             */
            if (!file_exists($this->tempFolder)) {
                throw new Exception($this->tempFolder . ' directory not exists!');
            }

            /**
             * iDap Auth Type
             * For ID and secret: Basic BASE64(deployment_id:deployment_secret)
             * For OAuth2: Bearer access_token
             */
            if (empty($this->authType)) {
                throw new Exception('Auth Type Not defined yet.');
            }

            $curl = new Curl();
            if ($this->authType == "basic") {
                $curl->setBasicAuthentication($this->deploymentId, $this->deploymentSecret);
            } elseif ($this->authType == "oAuth2") {
                exit('OAuth2 is not implemented yet');
            } else {
                exit('You have to set up auth Type = basic');
            }

            /**
             * Execute CURL call for the deployment, endpoint, tenant, token data
             */
            $curl->get(self::AUTH_ENDPOINT);

            /**
             * Handle all error code except cUrlHttpStatusCode = 200
             */
            if ($curl->error) {
                /**
                 *  Update-JobStatus with:
                 *      i. Event_type = “Error”
                 *      ii. Source = AUTH_SERVICE_NAME
                 *      iii. Event_id = 1000+[status code from request]
                 *      iv. Message = “Sync Agent was unable to retrieve an authorization token.”
                 *      v. Job_status
                 *      vi. Job_instance
                 *      vii. Metadata
                 */

                //TODO :: How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Error',
                    'Source' => self::AUTH_SERVICE_NAME,
                    'Event_id' => 1000 + $curl->getHttpStatusCode(),
                    'Message' => 'Sync Agent was unable to retrieve an authorization token.',
                    'Job_status' => NULL,
                    'Job_instance' => NULL,
                    'Metadata' => NULL
                ]);

                /**
                 * Throw an error that includes the status code and response payload
                 */
                $errorResponse = [
                    'httpStatusCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                    'responseBody' => json_decode($curl->getRawResponse())
                ];
            } elseif ($curl->getHttpStatusCode() == 200) {
                // If cUrl respose status code = 200

                // Decode json object
                $responseBody = json_decode($curl->getRawResponse());

                // If response short = ok
                if ($responseBody->short == 'ok') {

                    // Get content from response Body
                    $responseBody = $responseBody->content;

                    $this->endpoint = $responseBody->endpoint;
                    $this->tenant = $responseBody->tenant;
                    $this->deployment = $responseBody->deployment;
                    $this->token = $responseBody->token;

                    $syncResonse = $this->getSyncJobFromRemote($responseBody);

                    print_r($syncResonse);
                }
            }
        } catch (Exception $e) {
            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTrace()
            ];
        } catch (InvalidArgumentException $e) {
            $errorResponse =  [
                'httpStatusCode' => NULL,
                'errorMessage' => 'Invalid Argument: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            /**
             * Always Return success / failure
             */
            if ($errorResponse) {
                print_r(json_encode($errorResponse));
            }
        }
    }

    private function syncJobUpdate($statusParams)
    {
        // Define Vars
        $errorResponse = [];
        $updateStatus = FALSE;

        try {
            /**
             *  1. PATCH at “SYNC_ENDPOINT/job/TENANT_ID/DEPLOYMENT_ID/JOB_INSTANCE” with:
             *      a. Authorization header value of “Bearer SYNC_TOKEN”
             *      b. Content-Type header value of “application/json”
             *      c. Body: {
             *          “type”: EVENT_TYPE,
             *          “message”: MESSAGE,
             *          “event_id”: EVENT_ID,
             *          “source”: SOURCE,
             *          “status”: JOB_STATUS,
             *          “metadata”: METADATA
             *      }
             */

            // TODO:: To make Status Update API call job_instance_uuid is required
            if (empty($statusParams['Job_instance'])) {
                throw new Exception('job_instance_uuid is required to make API Call.');
            }

            $curl = new Curl();
            // TODO:: Before getting token there is no way to call Status Update API. If then have to improvised some logic
            // $curl->setBasicAuthentication($this->deploymentId, $this->deploymentSecret);
            $curl->setHeader('Authorization', 'Bearer ' . $this->token);
            $curl->setHeader('Content-Type', 'application/json');

            // Prepare jobURI for syncJobs
            $jobURI = 'https://' . $this->endpoint . DIRECTORY_SEPARATOR . 'job' . DIRECTORY_SEPARATOR . $this->tenant . DIRECTORY_SEPARATOR . $this->deployment . DIRECTORY_SEPARATOR . $statusParams['Job_instance'];

            /**
             * Make curl request to update Job Status
             */
            $curl->patch($jobURI, [
                'type' => empty($statusParams['Event_type']) ? NULL : $statusParams['Event_type'],
                'message' => empty($statusParams['Message']) ? NULL : $statusParams['Message'],
                'event_id' => empty($statusParams['Event_id']) ? NULL : $statusParams['Event_id'],
                'source' => empty($statusParams['Source']) ? NULL : $statusParams['Source'],
                'status' => empty($statusParams['Job_status']) ? NULL : $statusParams['Job_status'],
                'metadata' => empty($statusParams['Metadata']) ? NULL : $statusParams['Metadata'],
            ]);

            if ($curl->error) {
                /**
                 * Throw an error with the response code and response body
                 */
                $errorResponse = [
                    'httpStatusCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                    'responseBody' => $curl->getRawResponse()
                ];
            } elseif ($curl->getHttpStatusCode() == 200) {
                // Removed later
                $errorResponse = $statusParams;

                $updateStatus = TRUE;
            }

            /**
             * TODO:: If request fails, retry up to 5 times
             */
        } catch (Exception $e) {
            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTrace()
            ];
        } finally {
            if ($errorResponse) {
                // echo json_encode($errorResponse);

                // Always Return success / failure
                return $updateStatus;
            }
        }
    }

    private function getSyncJobFromRemote()
    {
        try {
            $curl = new Curl();
            $curl->setHeader('Authorization', 'Bearer ' . $this->token);

            /**
             * Prepare URI for syncJobs & Make curl request to get syncJobs
             */
            $jobURI = 'https://' . $this->endpoint . DIRECTORY_SEPARATOR . 'poll' . DIRECTORY_SEPARATOR . $this->tenant . DIRECTORY_SEPARATOR . $this->deployment;
            $curl->get($jobURI);

            if ($curl->error) {
                /**
                 *  Update-JobStatus with:
                 *      i. Event_type = “Error”
                 *      ii. Source = JOB_DISPATCH_NAME
                 *      iii. Event_id = 2000+[status code from request]
                 *      iv. Message = “Sync Agent was unable to retrieve sync jobs from the IDaP.”
                 */

                //TODO :: How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Error',
                    'Source' => self::JOB_DISPATCH_NAME,
                    'Event_id' => 2000 + $curl->getHttpStatusCode(),
                    'Message' => 'Sync Agent was unable to retrieve sync jobs from the IDaP.',
                    'Job_status' => NULL,
                    'Job_instance' => NULL,
                    'Metadata' => NULL
                ]);

                // Throw an error with the response code and response body, then exit
                return [
                    'httpStatusCode' => $curl->errorCode,
                    'errorMessage' => 'Sync Agent was unable to retrieve sync jobs from the IDaP. ' . $curl->errorMessage,
                    'responseBody' => $curl->getRawResponse()
                ];
            } elseif ($curl->getHttpStatusCode() == 200) {
                // Get content from response Body
                $responseSyncJob = json_decode($curl->getRawResponse(), true)['sync_jobs'];

                // Check syncJob has Jobs to executes
                if ($responseSyncJob) {
                    // 
                    $stop = 1;
                    $arJobInstance = [];
                    foreach ($responseSyncJob as $syncJob) {

                        // has to be removed later
                        if (in_array($stop, [1])) {
                            $stop++;
                            continue;
                        }

                        // Calling DB Server
                        $resData = $this->syncSqlServer($syncJob);

                        // If status is true & rowCount is greater then 0
                        if ($resData['status'] && $resData['rowCount'] > 0) {

                            $csvFilePath = $this->tempFolder . $syncJob['job_instance_uuid'] . $this->extFile;

                            /**
                             * Write CSv file to Disk. 
                             */
                            $fp = fopen($csvFilePath, 'w');
                            if ($fp) {
                                foreach ($resData['fetchAll'] as $row) {
                                    if (!isset($headings)) {
                                        $headings = array_keys($row);
                                        fputcsv($fp, $headings, ',', '"');
                                    }
                                    fputcsv($fp, $row, ',', '"');
                                }

                                fclose($fp);
                                unset($headings);
                            }

                            // 
                            array_push($arJobInstance, $syncJob['job_instance_uuid']);

                            /**
                             * If extraction is successful:
                             * a. Save the result set to [job.job_instance_uuid].csv in the TEMP_FOLDER location.
                             * b. Update-JobStatus with:
                             * i. Event_type = “Info”
                             * ii. Source = SQL_SERVICE_NAME
                             * iii. Event_id = 3002
                             * iv. Message = “Successfully executed SQL and saved output.”
                             * v. Job_instance = job.job_instance_uuid
                             * vi. Metadata = { “rows”: [count of rows extracted] }
                             * 
                             */

                            //TODO :: How to handle Job status update response
                            $this->syncJobUpdate([
                                'Event_type' => 'Info',
                                'Source' => self::SQL_SYNC_SERVICE,
                                'Event_id' => 3002,
                                'Message' => 'Successfully executed SQL and saved output.',
                                'Job_status' => NULL,
                                'Job_instance' => $syncJob['job_instance_uuid'],
                                'Metadata' => [
                                    'rows' => $resData['rowCount']
                                ]
                            ]);
                        } else {
                            // At this stage syncSqlServer function throw an error. WHat to do next?
                            // Now it's just skkiping to next row
                            // TODO:: 
                            // throw new Exception($resData['errorMessage']);
                            print_r($resData);
                        }

                        // has to be removed later
                        if ($stop < 2) {
                            $stop++;
                        } else {
                            break;
                        }
                    }

                    /**
                     * Return the path to the CSV file.
                     */
                    // return $arCsvFile;

                    if ($arJobInstance) {
                        $this->syncJobUpload($arJobInstance);
                    }
                } else {
                    // If no Jobs is waiting to execute then exit Gracefully
                    return [
                        'httpStatusCode' => NULL,
                        'errorMessage' => 'No sync Job waiting to execute.'
                    ];
                }
            }
        } catch (Exception $e) {
            /**
             * If extraction is unsuccessful:
             * Update-JobStatus with:
             * i. Event_type = “Error”
             * ii. Source = SQL_SERVICE_NAME
             * iii. Event_id = 3602
             * iv. Message = “An error was encountered while extracting data: \n[ERROR_DETAILS]”
             * v. Job_status = “failed”
             * vi. Job_instance = job.job_instance_uuid
             * vii. Metadata = convert stack trace (if possible) to JSON object
             */

            //TODO :: How to handle Job status update response
            $this->syncJobUpdate([
                'Event_type' => 'Error',
                'Source' => self::SQL_SYNC_SERVICE,
                'Event_id' => 3602,
                'Message' => 'An error was encountered while extracting data: \n' . $e->getMessage(),
                'Job_status' => 'failed',
                'Job_instance' => '', //$syncJob['job_instance_uuid'],
                'Metadata' => $e->getTraceAsString()
            ]);

            // return [
            //     'httpStatusCode' => NULL,
            //     'errorMessage' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ];
        }
    }

    private function syncJobUpload($arJobInstance)
    {

        $curl = new Curl();
        $curl->setHeader('Authorization', 'Bearer ' . $this->token);

        foreach ($arJobInstance as $jobInstance) {
            // Prepare jobURI for syncJobs
            $jobURI = 'https://' . $this->endpoint . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $this->tenant . DIRECTORY_SEPARATOR . $this->deployment . DIRECTORY_SEPARATOR . $jobInstance;

            /**
             * Get Azure fule upload URL
             */
            $curl->get($jobURI);

            if ($curl->error) {
                /**
                 *  a. Update-JobStatus with:
                 *      i. Event_type = “Error”
                 *      ii. Source = JOB_MANAGER_NAME
                 *      iii. Event_id = 2000+[status code from request]
                 *      iv. Message = “Sync agent encountered an error while retrieving upload URI.”
                 *      v. Job_status = “failed”
                 *      vi. Job_instance = JOB_INSTANCE
                 *  b. Throw an error with the response code and response body, then exit
                 */

                //TODO :: How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Error',
                    'Source' => self::JOB_MANAGER_NAME,
                    'Event_id' => 2000 +  $curl->getHttpStatusCode(),
                    'Message' => 'Sync agent encountered an error while retrieving upload URI.',
                    'Job_status' => 'failed',
                    'Job_instance' => $jobInstance,
                    'Metadata' => NULL
                ]);

                // It's can't be exited. We have to complete this loop
                $errorResponse = [
                    'httpStatusCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                    'responseBody' => $curl->getRawResponse()
                ];
                // print_r($errorResponse);
            } elseif ($curl->getHttpStatusCode() == 200) {
                /**
                 * Update-JobStatus with:
                 *  a. Event_type = “Info”
                 *  b. Source = JOB_MANAGER _NAME
                 *  c. Event_id = 2002
                 *  d. Message = “Retrieved upload URI. Commencing upload.”
                 *  e. Job_status = “uploading”
                 *  f. Job_instance = JOB_INSTANCE
                 */
                //TODO :: How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Info',
                    'Source' => self::JOB_MANAGER_NAME,
                    'Event_id' => 2002,
                    'Message' => 'Retrieved upload URI. Commencing upload.',
                    'Job_status' => 'uploading',
                    'Job_instance' => $jobInstance,
                    'Metadata' => NULL
                ]);

                $azureResponse = json_decode($curl->getRawResponse(), true);

                if ($this->testAzure == false) {
                    // print_r($azureResponse);
                    // exit;
                }

                $arSasUrl = parse_url($azureResponse['sas_url']);
                parse_str($arSasUrl['query'], $arTokenParams);

                $blobEndpoint = $arSasUrl['scheme'] . '://' . $arSasUrl['host'] . $arSasUrl['path'];
                // $signeture = $arSasUrl['query'];
                // $signeture = '?' . $arSasUrl['query'];
                // $signeture = '?' . urldecode($arSasUrl['query']);
                $signeture = $arTokenParams['sig'];

                $containerName = explode('/', $arTokenParams['path'])[1];

                # Setup a specific instance of an Azure::Storage::Client
                $azureConString = "BlobEndpoint=$blobEndpoint;SharedAccessSignature=$signeture";

                // Create blob client.
                $blobRestProxy = BlobRestProxy::createBlobService($azureConString);
                // print_r($blobRestProxy);exit;

                try {
                    $csvFilePath = $this->tempFolder . $jobInstance . $this->extFile;
                    $csvContent = fopen($csvFilePath, 'r');

                    $blockBlobOptions = new CreateBlockBlobOptions();
                    $blockBlobOptions->setMetadata([
                        'version' => $arTokenParams['sv'],
                        // 'Content-Length' => filesize($csvFilePath),
                        'date' => date('Y-m-d'),
                        // 'cache-control' => 'public, max-age=259200',
                    ]);

                    //Upload blob
                    $blobRestProxy->createBlockBlob($containerName, $jobInstance . $this->extFile, $csvContent, $blockBlobOptions);

                    print_r($blobRestProxy);
                    exit;
                } catch (Exception $e) {
                    // Handle exception based on error codes and messages.
                    // Error codes and messages are here: 
                    // http://msdn.microsoft.com/en-us/library/windowsazure/dd179439.aspx
                    echo $e->getCode() . ": " . $e->getMessage() . "<br />";
                } catch (ServiceException $e) {
                    // Handle exception based on error codes and messages.
                    // Error codes and messages are here:
                    // http://msdn.microsoft.com/library/azure/dd179439.aspx
                    $code = $e->getCode();
                    $error_message = $e->getMessage();
                    echo $code . ": " . $error_message . "<br />";
                }

                exit;
            }

            print_r($curl->getRawResponse());
        }
        exit;
    }

    private function syncSqlServer($syncJob)
    {
        try {
            /**
             *  If the job has a template_override value with a sql value, execute this SQL against the database;
             *  If the job has no override value, execute the syncTemplate.sql value against the database.
             */
            $sql = is_null($syncJob['template_override']) ? $syncJob['syncTemplate']['sql'] : $syncJob['template_override'];

            $hostIp = '20.40.72.166'; //$config['host']
            $hostPort = $syncJob['config']['port'];
            $dbName = $syncJob['config']['database'];

            $dsn = "sqlsrv:Server=$hostIp,$hostPort;Database=$dbName";
            $conn = new PDO($dsn, $syncJob['config']['username'], $syncJob['config']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            if ($conn) {
                /**
                 *  If connection Success Update-JobStatus with:
                 *      i. Event_type = “Info”
                 *      ii. Source = SQL_SERVICE_NAME
                 *      iii. Event_id = 3000
                 *      iv. Message = “Connecting to [job.syncTemplate.syncSource.name] and beginning data extraction.”
                 *      v. Job_status = “extracting”
                 *      vi. Job_instance = job.job_instance_uuid
                 *      vii. Metadata
                 */

                //TODO :: How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Info',
                    'Source' => self::SQL_SYNC_SERVICE,
                    'Event_id' => 3000,
                    'Message' => 'Connecting to ' . $syncJob['syncTemplate']['syncSource']['name'] . ' and beginning data extraction.',
                    'Job_status' => 'extracting',
                    'Job_instance' => $syncJob['job_instance_uuid'],
                    'Metadata' => NULL
                ]);

                $stmt = $conn->query($sql);

                return [
                    'status' => TRUE,
                    'fetchAll' => $stmt->fetchAll(),
                    'rowCount' => $stmt->rowCount()
                ];
            }
        } catch (PDOException $e) {
            /**
             *  If connection fails, return an error.
             */
            return [
                'status' => FALSE,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    private function generateRandomString($length = 6)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}