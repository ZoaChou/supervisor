<?php

namespace Indigo\Supervisor\EventListener;

use Indigo\Supervisor\Event\Event;
use Indigo\Supervisor\Event\EventInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractEventListener implements EventListenerInterface, LoggerAwareInterface
{
    /**
     * Psr logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Input stream
     *
     * @var resource
     */
    protected $inputStream = STDIN;

    /**
     * Output stream
     *
     * @var resource
     */
    protected $outputStream = STDOUT;

    /**
     * Set input stream
     *
     * @param resource $stream
     */
    public function setInputStream($stream)
    {
        if (is_resource($stream)) {
            $this->inputStream = $stream;
        } else {
            throw new \InvalidArgumentException('Invalid resource for input stream');
        }
    }

    /**
     * Set output stream
     *
     * @param resource $stream
     */
    public function setOutputStream($stream)
    {
        if (is_resource($stream)) {
            $this->inputStream = $stream;
        } else {
            throw new \InvalidArgumentException('Invalid resource for output stream');
        }
    }

    /**
     * Listen for events
     */
    public function listen()
    {
        $this->statusReady();

        while (true) {
            if (!$event = $this->getEvent()) {
                continue;
            }

            $result = $this->doListen($event);

            if (!$this->processResult($result)) {
                return;
            }

            $this->statusReady();
        }
    }

    /**
     * Get event from input stream
     *
     * @return Event|false Event object
     */
    protected function getEvent()
    {
        $event = false;

        if ($headers = $this->read()) {
            $headers = $this->parseData($headers);

            $payload = $this->read($headers['len']);
            $payload = explode("\n", $payload, 2);
            isset($payload[1]) or $payload[1] = null;

            list($payload, $body) = $payload;

            $event = $this->resolveEvent(
                $headers,
                $this->parseData($payload),
                $body
            );
        }

        return $event;
    }

    protected function resolveEvent(array $headers = array(), array $payload = array(), $body = null)
    {
        return new Event($headers, $payload, $body);
    }

    /**
     * Process result
     *
     * @param  integer $result Result code
     * @return boolean Listener should exit or not
     */
    protected function processResult($result)
    {
        switch ($result) {
            case 0:
                $this->write(self::OK);
                break;
            case 1:
                $this->write(self::FAIL);
                break;
            default:
                return false;
                break;
        }

        return true;
    }

    /**
     * Print ready status to output stream
     */
    protected function statusReady()
    {
        $this->write(self::READY);
    }

    /**
     * Do the actual event handling
     *
     * @param  EventInterface   $event
     * @return integer 0=success, 1=failure
     */
    abstract protected function doListen(EventInterface $event);

    /**
     * Parse colon devided data
     *
     * @param  string $rawData
     * @return array
     */
    protected function parseData($rawData)
    {
        $outputData = array();

        foreach (explode(' ', $rawData) as $data) {
            $data = explode(':', $data);
            $outputData[$data[0]] = $data[1];
        }

        return $outputData;
    }

    /**
     * Read data from input stream
     *
     * @param  integer $length If given read this size of bytes, read a line anyway
     * @return string
     */
    protected function read($length = null)
    {
        if (is_null($length)) {
            return trim(fgets($this->inputStream));
        } else {
            return fread($this->inputStream, $length);
        }
    }

    /**
     * Write data to output stream
     *
     * @param  string $value
     */
    protected function write($value)
    {
        return @fwrite($this->outputStream, $value);
    }

    /**
     * Sets a logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
