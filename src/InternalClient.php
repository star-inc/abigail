<?php

// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

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
        $encoder = $this->getOutputFormatEncoder($this->getOutputFormat());
        return $encoder($pMessage);
    }
}
