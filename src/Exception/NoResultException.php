<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager\Exception;

use Doctrine\ORM\NoResultException as ORMNoResultException;

class NoResultException extends ORMNoResultException implements HttpStatusInterface
{
    /**
     * @var int
     */
    protected $statusCode = 404;

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
