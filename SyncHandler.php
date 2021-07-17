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

    public function __construct()
    {
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
    }

    public function iscSyncAuth()
    {

        // dummy call
        $curlD = new Curl();
        $curlD->get('https://is-onprem-sync-au-vic.azurewebsites.net/reset?code=OW1ODVfMAaTd5EX3iN0CwiSWYeqS5sOobk8MuSiXmhmDjD174QjbRQ==');

        try {
            $curl = new Curl();
            $curl->setBasicAuthentication($this->deploymentId, $this->deploymentSecret);
            $curl->get(self::AUTH_ENDPOINT);
            if ($curl->error) {
                echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
            } elseif ($curl->getHttpStatusCode() == 200) {
                // Get content from response Body
                $responseBody = json_decode($curl->getRawResponse())->content;

                // 
                $this->getSyncJobFromRemote($responseBody);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        } catch (InvalidArgumentException $e) {
            echo $e->getMessage();
        }
    }

    private function getSyncJobFromRemote($responseBody)
    {
        try {

            $filePath = 'https://' . $responseBody->endpoint . DIRECTORY_SEPARATOR . 'poll' . DIRECTORY_SEPARATOR . $responseBody->tenant . DIRECTORY_SEPARATOR . $responseBody->deployment;

            $curl = new Curl();
            $curl->setHeader('Authorization', 'Bearer ' . $responseBody->token);
            $curl->get($filePath);
            if ($curl->error) {
                echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
            } elseif ($curl->getHttpStatusCode() == 200) {
                // Get content from response Body
                $responseSyncJob = json_decode($curl->getRawResponse(), true)['sync_jobs'];

                // 
                $stop = 1;
                foreach ($responseSyncJob as $syncJob) {
                    if ($syncJob) {
                        $resData = $this->readData($syncJob['config'], $syncJob['syncTemplate']['sql']);
                        if ($resData) {
                            $fp = fopen($this->tempFolder . $syncJob['job_instance_uuid'] . '.csv', 'w');

                            foreach ($resData as $row) {
                                if (!isset($headings)) {
                                    $headings = array_keys($row);
                                    fputcsv($fp, $headings, ',', '"');
                                }
                                fputcsv($fp, $row, ',', '"');
                            }

                            fclose($fp);
                            unset($headings, $headings);
                        }
                    }

                    if ($stop < 6) {
                        $stop++;
                    } else {
                        break;
                    }
                }

                echo 'Total ' . $stop . ' CSV file write in to the disk. ';
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        } catch (InvalidArgumentException $e) {
            echo $e->getMessage();
        }
    }

    public function getSqlResponse()
    {
        $config = [
            'host' => '20.40.72.166',
            'port' => 1433,
            'database' => 'CISNet3',
            'username' => 'intellischool-sync',
            'password' => 'Oliv3r16!'
        ];

        $sql = "select distinct '' as ttday_uuid, '$tnt_uuid' as tnt_uuid, t.timetablegroup + '|' + yeardescription + '|' + 'Semester ' + cast(legacyfilesemester as varchar(1)) + '|Grading Period' + '|' + 'DEFAULT' as ttdef_ext_id, format(timetabledate, 'yyyyMMdd') + '|' + t.timetablegroup as ttday_ext_id, t.timetablegroup + '|' + format(timetabledate, 'yyyy-MM-dd') ttday_code, format(cast(timetabledate as date), 'yyyy-MM-dd') as timetable_day, format(timetabledate, 'dddd') as day_title, '' as period_join, t.timetablegroup as timetable_group, '{}' as custom_fields, '{}' as custom_flags, FORMAT(GetUtcDate(), 'yyyy-MM-ddTHH:mm:ssZ') updated from dbo.timetabledefinition t join dbo.LuCampus c on t.TimetableGroup = c.TimetableGroup join AcademicSemesters acs on acs.ID = t.FileSeq join academicyears ay on acs.AcademicYearID = ay.id where 1 = 1 and cast(timetabledate as date) >= getdate() -5";

        $resData = $this->readData($config, $sql);
        if ($resData) {
            $fp = fopen($this->tempFolder . $this->authStartdAt . 'test.csv', 'w');
            foreach ($resData as $row) {
                if (!isset($headings)) {
                    $headings = array_keys($row);
                    fputcsv($fp, $headings, ',', '"');
                }
                fputcsv($fp, $row, ',', '"');
            }

            fclose($fp);
        }
    }

    private function readData($config, $sql)
    {
        try {
            $hostIp = '20.40.72.166'; //$config['host']
            $hostPort = $config['port'];
            $dbName = $config['database'];

            $dsn = "sqlsrv:Server=$hostIp,$hostPort;Database=$dbName";
            $conn = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            if ($conn) {
                return $conn->query($sql)->fetchAll();
            }
        } catch (PDOException $e) {
            echo ("Error connecting to SQL Server: " . $e->getMessage());
        }
    }
}
