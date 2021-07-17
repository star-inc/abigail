<?php

namespace Abigail;

/**
 * This client does not send any HTTP data,
 * instead it just returns the value.
 *
 * Good for testing purposes.
 */
class InternalClient extends Client
{
    public function sendResponse(string $pHttpCode, $pMessage)
    {
        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = intval($pHttpCode);
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatMethod($this->getOutputFormat());

        return $this->$method($pMessage);
    }
}
