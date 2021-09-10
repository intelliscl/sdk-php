<?php

namespace Intellischool;

/**
 * A version of Stream that doesn't close the file on calling close, so it can be reused.
 */
class NonClosingStream extends \GuzzleHttp\Psr7\Stream
{
    /**
     * Rewinds to start of file and detaches
     */
    public function close(): void
    {
        $this->rewind();
        $this->detach();
    }

}