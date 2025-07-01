<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager\Exception;

interface HttpStatusInterface
{
    /**
     * Return http code status
     * @return int
     */
    public function getStatusCode(): int;
}
