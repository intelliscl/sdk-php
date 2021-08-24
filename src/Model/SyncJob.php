<?php

namespace Intellischool\Model;

/*
 * {
 *   “job_definition_uuid”: “UUID”,
 *   “job_instance_uuid”: “UUID”,
 *   “syncTemplate”: {
 *       “sql”: “SELECT * FROM TABLE”,
 *       “syncSource”: {
 *           “name”: “Source name”,
 *           “type”: “MSSQL”
 *       }
 *   },
 *   “connection_string”: “Server = 10.150.120.140,1433; Database = CISNet3; Integrated Security = false; User ID = intellischool; Password = <password>;”
 *   “template_override”: {
 *       “sql”: “SELECT * FROM TABLE”
 *   }
 * }
 */

use Intellischool\IntelliSchoolException;

/**
 * Helper for the Job Definition JSON data
 *
 * @property-read string $definitionId The job_definition_uuid
 * @property-read string $instanceId The job_instance_uuid
 * @property-read string $query The SQL to retrieve data with. Either syncTemplate->sql or template_override if present.
 * @property-read string $sourceType The Sync Source Type
 */
class SyncJob
{
    public object $jsonData;

    public function __construct(object $json)
    {
        if (empty($json)) {
            throw new IntelliSchoolException('Empty SyncJob data');
        }
        $this->jsonData = $json;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'definitionId':
                return $this->jsonData->job_definition_uuid;
            case 'instanceId':
                return $this->jsonData->job_instance_uuid;
            case 'query':
                return !empty($this->jsonData->template_override->sql) ? $this->jsonData->template_override->sql : $this->jsonData->syncTemplate->sql;
            case 'sourceType':
                return $this->jsonData->syncTemplate->syncSource->type;
        }
        return null;
    }

    public function getPdoString($timeout): string {
        $config = $this->jsonData->config;

        switch ($this->sourceType) {
            case 'MSSQL':
                $dsn = 'sqlsrv:Server=' . $config->host;
                if (!empty($config->port)) {
                    $dsn .= ','.$config->port;
                }
                $dsn .= ';Database='.$config->database;
                $dsn .= ';LoginTimeout='.$timeout;
                break;
            case 'MYSQL':
            case 'PGSQL':
                $dsn = strtolower($this->sourceType).':host='.$config->host;
                if (!empty($config->port)) {
                    $dsn .= ';port='.$config->port;
                }
                $dsn .= ';dbname='.$config->database;
                break;
            default:
                throw new IntelliSchoolException('Unsupported sync source: '.$this->sourceType);
        }
        return $dsn;
    }
}