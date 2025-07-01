<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager;

use ATPawelczyk\ObjectManager\Exception\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\UuidInterface;

/**
 * Interface ObjectFinderInterface
 * @package ObjectManager
 */
interface ObjectFinderInterface
{
    const DEFAULT_PAGE_SIZE = 30;

    /**
     * @param array $search
     * @return ObjectFinderInterface
     */
    public function filter(array $search): ObjectFinderInterface;

    /**
     * @param int|null $offsetResult
     * @return ObjectFinderInterface
     */
    public function offsetResult(?int $offsetResult): ObjectFinderInterface;

    /**
     * @param int $page
     * @return ObjectFinderInterface
     */
    public function offsetPageResult(int $page): ObjectFinderInterface;

    /**
     * @param int|null $maxResult
     * @return ObjectFinderInterface
     */
    public function maxResult(?int $maxResult): ObjectFinderInterface;

    /**
     * @param string ...$fields
     * @return ObjectFinderInterface
     */
    public function select(string ...$fields): ObjectFinderInterface;

    /**
     * @param string ...$fields
     * @return ObjectFinderInterface
     */
    public function addSelect(string ...$fields): ObjectFinderInterface;

    /**
     * @param string ...$fields
     * @return ObjectFinderInterface
     */
    public function groupBy(string ...$fields): ObjectFinderInterface;

    /**
     * @param string|Expr\OrderBy $sort
     * @param string|null $order
     * @return ObjectFinderInterface
     */
    public function order($sort, ?string $order = null): ObjectFinderInterface;

    /**
     * @param string $name
     * @param mixed $value
     * @return ObjectFinderInterface
     */
    public function filterField(string $name, $value): ObjectFinderInterface;

    /**
     * @param string      $join          The relationship to join.
     * @param string      $alias         The alias of the join.
     * @param string|null $conditionType The condition type constant. Either ON or WITH.
     * @param string|null $condition     The condition for the join.
     * @param string|null $indexBy       The index for the join.
     * @return ObjectFinderInterface
     */
    public function leftJoin(string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): ObjectFinderInterface;

    /**
     * @param string      $join          The relationship to join.
     * @param string      $alias         The alias of the join.
     * @param string|null $conditionType The condition type constant. Either ON or WITH.
     * @param string|null $condition     The condition for the join.
     * @param string|null $indexBy       The index for the join.
     * @return ObjectFinderInterface
     */
    public function join(string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): ObjectFinderInterface;

    /**
     * Bindowanie warunków do zapytania
     * Aby użyć aliasu do bieżącej instancji wystarczy użyć t.
     * where('t.pole = :parametr100 OR t.pole2 IS NOT NULL', 100)
     * @param string $condition
     * @param mixed ...$parameters
     * @return ObjectFinderInterface
     */
    public function where(string $condition, ...$parameters): ObjectFinderInterface;

    /**
     * @param string $key   The parameter position or name.
     * @param mixed          $value The parameter value.
     * @param string|integer|null $type
     * @return ObjectFinderInterface
     */
    public function setParameter(string $key, $value, $type = null): ObjectFinderInterface;

    /**
     * @return ObjectFinderInterface
     */
    public function contextLimitation(): ObjectFinderInterface;

    /**
     * @param UuidInterface $uuid
     * @param int|null $hydration
     * @return object|mixed[]|null
     */
    public function findByUuid(UuidInterface $uuid, ?int $hydration = null);

    /**
     * @param mixed $id
     * @param string|int|null $hydration
     * @return object|array|null
     */
    public function find($id, $hydration = null);

    /**
     * @param array $search
     * @param string|int|null $hydration
     * @return object|array|null
     */
    public function findBy(array $search = [], $hydration = null);

    /**
     * @param string $field
     * @param bool $distinct
     * @param array $search
     * @return int
     */
    public function count(string $field, bool $distinct = true, array $search = []): int;

    /**
     * @param mixed $id
     * @param int|string|null $hydration
     * @return object|array
     * @throws NoResultException
     */
    public function findOrDie($id, $hydration = null);

    /**
     * @param mixed $id
     * @return array
     * @throws NoResultException
     */
    public function findOrDieAsArray($id): array;

    /**
     * @param array $search
     * @param int|string|null $hydration
     * @return object[]
     */
    public function findAll(array $search = [], $hydration = null): array;

    /**
     * @param array $search
     * @return array
     */
    public function findAllAsArray(array $search = []): array;

    /**
     * @param array $search
     * @param int|string|null $hydration
     * @return array
     */
    public function findAllDetached(array $search = [], $hydration = null): iterable;

    /**
     * @return Query
     */
    public function query(): Query;

    /**
     * @return QueryBuilder
     */
    public function queryBuilder(): QueryBuilder;

    /**
     * @return string
     */
    public function getAlias(): string;
}
