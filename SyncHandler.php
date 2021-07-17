<?php

require 'autoload.php';

use \Curl\Curl;
use \Dotenv\Dotenv;

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
    }

    public function iscSyncAuth()
    {

        // dummy call This should be removed end of the development.
        $curlD = new Curl();
        $curlD->get('https://is-onprem-sync-au-vic.azurewebsites.net/reset?code=OW1ODVfMAaTd5EX3iN0CwiSWYeqS5sOobk8MuSiXmhmDjD174QjbRQ==');

        // Define Null
        $errorResponse = NULL;

        try {
            //Check tempFolder is exist if not then create it
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
                exit('You have to set up auth Type = basic.');
            }

            // Execute CURL call for the deployment, endpoint, tenant, token data
            $curl->get(self::AUTH_ENDPOINT);

            // Will handle all error code except cUrlHttpStatusCode = 200
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
                $this->syncJobUpdate([
                    'Event_type' => 'Error',
                    'Source' => self::AUTH_SERVICE_NAME,
                    'Event_id' => 1000 . $curl->getHttpStatusCode(),
                    'Message' => 'Sync Agent was unable to retrieve an authorization token.',
                    'Job_status' => NULL,
                    'Job_instance' => NULL,
                    'Metadata' => NULL
                ]);

                // Throw an error that includes the status code and response payload, then exit.
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

                    $this->getSyncJobFromRemote($responseBody);
                }
            }
        } catch (Exception $e) {
            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } catch (InvalidArgumentException $e) {
            echo $e->getMessage();
        } finally {
            // Always Return success / failure
            if ($errorResponse) {
                $errorResponse['source'] = 'Error generated from: ' . __FUNCTION__;
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
                throw new Exception('job_instance_uuid is required to make API Call for ' . __FUNCTION__);
            }

            $curl = new Curl();
            // TODO:: Before getting token there is no way to call Status Update API. If then have to improvised some logic
            // $curl->setBasicAuthentication($this->deploymentId, $this->deploymentSecret);
            $curl->setHeader('Authorization', 'Bearer ' . $this->token);
            $curl->setHeader('Content-Type', 'application/json');

            // Prepare jobURI for syncJobs
            $jobURI = 'https://' . $this->endpoint . DIRECTORY_SEPARATOR . 'job' . DIRECTORY_SEPARATOR . $this->tenant . DIRECTORY_SEPARATOR . $this->deployment . DIRECTORY_SEPARATOR . $statusParams['Job_instance'];

            // Make curl request to get syncJobs
            $curl->patch($jobURI, [
                'type' => empty($statusParams['Event_type']) ? NULL : $statusParams['Event_type'],
                'message' => empty($statusParams['Message']) ? NULL : $statusParams['Message'],
                'event_id' => empty($statusParams['Event_id']) ? NULL : $statusParams['Event_id'],
                'source' => empty($statusParams['Source']) ? NULL : $statusParams['Source'],
                'status' => empty($statusParams['Job_status']) ? NULL : $statusParams['Job_status'],
                'metadata' => empty($statusParams['Metadata']) ? NULL : $statusParams['Metadata'],
            ]);

            if ($curl->error) {
                // Throw an error with the response code and response body, then exit
                $errorResponse = [
                    'httpStatusCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                    'responseBody' => $curl->getRawResponse()
                ];
            } elseif ($curl->getHttpStatusCode() == 200) {
                // will be removed later
                $errorResponse = [
                    'params' => $statusParams,
                    'responseBody' => $curl->getRawResponse()
                ];

                $updateStatus = TRUE;
            }

            // If request fails, retry up to 5 times

            
        } catch (Exception $e) {
            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            
            if ($errorResponse) {
                $errorResponse['source'] = 'Error generated from: ' . __FUNCTION__;
                echo json_encode($errorResponse);

                // Always Return success / failure
                return $updateStatus;
            }
        }
    }

    private function getSyncJobFromRemote()
    {
        // Define Null
        $errorResponse = NULL;

        try {
            // Prepare jobURI for syncJobs
            $jobURI = 'https://' . $this->endpoint . DIRECTORY_SEPARATOR . 'poll' . DIRECTORY_SEPARATOR . $this->tenant . DIRECTORY_SEPARATOR . $this->deployment;

            $curl = new Curl();
            $curl->setHeader('Authorization', 'Bearer ' . $this->token);

            // Make curl request to get syncJobs
            $curl->get($jobURI);

            if ($curl->error) {
                /**
                 *  Update-JobStatus with:
                 *      i. Event_type = “Error”
                 *      ii. Source = JOB_DISPATCH_NAME
                 *      iii. Event_id = 2000+[status code from request]
                 *      iv. Message = “Sync Agent was unable to retrieve sync jobs from the IDaP.”
                 */

                $this->syncJobUpdate([
                    'Event_type' => 'Error',
                    'Source' => self::JOB_DISPATCH_NAME,
                    'Event_id' => 2000 . $curl->getHttpStatusCode(),
                    'Message' => 'Sync Agent was unable to retrieve sync jobs from the IDaP.',
                    'Job_status' => NULL,
                    'Job_instance' => NULL,
                    'Metadata' => NULL
                ]);

                // Throw an error with the response code and response body, then exit
                $errorResponse = [
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
                    $arCsvFile = [];
                    foreach ($responseSyncJob as $syncJob) {

                        // Calling DB Server
                        $resData = $this->syncSqlServer($syncJob);
                        if ($resData) {

                            $csvFilePath = $this->tempFolder . $syncJob['job_instance_uuid'] . '.csv';

                            // Create instance buffer for csv file write
                            $fp = fopen($csvFilePath, 'w');
                            if ($fp) {
                                foreach ($resData as $row) {
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
                            array_push($arCsvFile, $csvFilePath);

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

                            $this->syncJobUpdate([
                                'Event_type' => 'Info',
                                'Source' => self::SQL_SYNC_SERVICE,
                                'Event_id' => 3002,
                                'Message' => 'Successfully executed SQL and saved output.',
                                'Job_status' => NULL,
                                'Job_instance' => $syncJob['job_instance_uuid'],
                                'Metadata' => [
                                    'rows' => count($resData) - 1, //Removing header from row count
                                ]
                            ]);
                        }

                        // Return the path to the CSV file.

                        if ($stop < 1) {
                            $stop++;
                        } else {
                            break;
                        }
                    }
                    print_r([$arCsvFile]);
                } else {
                    // If no Jobs is waiting to execute then exit Gracefully
                    $errorResponse = [
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
            $this->syncJobUpdate([
                'Event_type' => 'Error',
                'Source' => self::SQL_SYNC_SERVICE,
                'Event_id' => 3602,
                'Message' => 'An error was encountered while extracting data: \n' . $e->getMessage(),
                'Job_status' => 'failed',
                'Job_instance' => '', //$syncJob['job_instance_uuid'],
                'Metadata' => $e->getTraceAsString()
            ]);

            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } catch (InvalidArgumentException $e) {
            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => 'Invalid Argument: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            // Always Return success / failure
            if ($errorResponse) {
                $errorResponse['source'] = 'Error generated from: ' . __FUNCTION__;
                print_r(json_encode($errorResponse));
            }
        }
    }

    private function syncSqlServer($syncJob)
    {
        $errorResponse = NULL;

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

                // How to handle Job status update response
                $this->syncJobUpdate([
                    'Event_type' => 'Info',
                    'Source' => self::SQL_SYNC_SERVICE,
                    'Event_id' => 3000,
                    'Message' => 'Connecting to ' . $syncJob['syncTemplate']['syncSource']['name'] . ' and beginning data extraction.',
                    'Job_status' => 'extracting',
                    'Job_instance' => $syncJob['job_instance_uuid'],
                    'Metadata' => NULL
                ]);

                return $conn->query($sql)->fetchAll();
            }
        } catch (PDOException $e) {
            /**
             *  If connection fails, return an error.
             */
            // print_r($e);

            $errorResponse = [
                'httpStatusCode' => NULL,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } finally {
            // Always Return success / failure
            if ($errorResponse) {
                $errorResponse['source'] = 'Error generated from: ' . __FUNCTION__;
                print_r(json_encode($errorResponse));
            }
        }
    }
}
