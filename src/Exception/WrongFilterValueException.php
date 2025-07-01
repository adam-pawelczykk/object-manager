<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager\Exception;

use RuntimeException;
use Throwable;

/**
 * Class WrongFilterValueException
 * @package ObjectManager\Exception
 */
class WrongFilterValueException extends RuntimeException implements HttpStatusInterface
{
    /** @var int */
    protected $statusCode = 422;

    /**
     * WrongFilterValueException constructor.
     * @param string $parameter
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($parameter, $code = 0, Throwable $previous = null)
    {
        parent::__construct("Wrong value of filter parameter `{$parameter}`.", $code, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
