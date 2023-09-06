<?php

namespace Typesense\Exceptions;

use Exception;

/**
 * Class TypesenseClientError
 *
 * @package \Typesense\Exceptions
 * @date    4/5/20
 * @author  Abdullah Al-Faqeir <abdullah@devloops.net>
 */
class TypesenseClientError extends Exception
{

    public function setMessage(string $message): TypesenseClientError
    {
        $this->message = $message;
        return $this;
    }
}
