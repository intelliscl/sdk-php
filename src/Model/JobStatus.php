<?php

namespace Intellischool\Model;

class JobStatus implements \JsonSerializable
{
    /**
     * PHP Property => JSON property
     */
    private const PROPERTY_MAP = [
        'eventType'   => 'Event_type',
        'source'      => 'Source',
        'eventId'     => 'Event_id',
        'message'     => 'Message',
        'jobStatus'   => 'Job_status',
        'jobInstance' => 'Job_instance',
        'meta'        => 'Metadata'
    ];

    public string $eventType;
    public string $source;
    public int $eventId;
    public string $message;
    public ?string $jobStatus;
    public ?string $jobInstance;
    public ?object $meta;

    /**
     * @param string $eventType
     *
     * @return JobStatus
     */
    public function setEventType(string $eventType): JobStatus
    {
        $this->eventType = $eventType;
        return $this;
    }

    /**
     * @param string $source
     *
     * @return JobStatus
     */
    public function setSource(string $source): JobStatus
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @param int $eventId
     *
     * @return JobStatus
     */
    public function setEventId(int $eventId): JobStatus
    {
        $this->eventId = $eventId;
        return $this;
    }

    /**
     * @param string $message
     *
     * @return JobStatus
     */
    public function setMessage(string $message): JobStatus
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param string|null $jobStatus
     *
     * @return JobStatus
     */
    public function setJobStatus(?string $jobStatus): JobStatus
    {
        $this->jobStatus = $jobStatus;
        return $this;
    }

    /**
     * @param string|null $jobInstance
     *
     * @return JobStatus
     */
    public function setJobInstance(?string $jobInstance): JobStatus
    {
        $this->jobInstance = $jobInstance;
        return $this;
    }

    /**
     * @param object|null $meta
     *
     * @return JobStatus
     */
    public function setMeta(?object $meta): JobStatus
    {
        $this->meta = $meta;
        return $this;
    }

    //from JsonSerializable
    public function jsonSerialize()
    {
        $output = array();
        foreach (self::PROPERTY_MAP as $phpProp => $jsonProp)
        {
            if (!empty($this->{$phpProp}))
            {
                $output[$jsonProp] = $this->{$phpProp};
            }
        }
        return $output;
    }
}