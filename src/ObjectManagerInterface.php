<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager;

use Doctrine\Persistence\ObjectManager as DoctrineObjectManager;
use Doctrine\ORM\QueryBuilder;

/**
 * Interface ObjectManagerInterface
 * @package ObjectManager
 */
interface ObjectManagerInterface extends DoctrineObjectManager
{
    /**
     * Gets finder for a class.
     * @param string $className
     * @param string|null $alias
     * @return ObjectFinderInterface
     */
    public function getFinder(string $className, ?string $alias = null): ObjectFinderInterface;

    /**
     * @param string $className
     * @param mixed $id
     * @return object
     */
    public function findOrDie(string $className, $id): object;

    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     * @param string $className
     * @param string $alias
     * @param null $indexBy
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $className, string $alias, $indexBy = null): QueryBuilder;

    /**
     * Tells the ObjectManager to make an instance managed and persistent.
     * The object will be entered into the database as a result of the flush operation.
     *
     * NOTE: The persist operation always considers objects that are not yet known to
     * this ObjectManager as NEW. Do not pass detached objects to the persist operation.
     *
     * @param object ...$objects The instance to make managed and persistent.
     * @return void
     */
    public function persistAll(object ...$objects): void;

    /**
     * Removes an object instance.
     * A removed object will be removed from the database as a result of the flush operation.
     *
     * @param object ...$objects The object instance to remove.
     * @return void
     */
    public function removeAll(object ...$objects): void;
}
