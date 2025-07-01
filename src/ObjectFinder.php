<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager;

use Doctrine\ORM\Mapping\ClassMetadata;
use ATPawelczyk\ObjectManager\Exception\NoResultException;
use ATPawelczyk\ObjectManager\Exception\WrongFilterValueException;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use InvalidArgumentException;
use LogicException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Safe\Exceptions\PcreException;
use TypeError;

/**
 * Class ObjectFinder
 * @package ObjectManager
 * @template T of object
 */
class ObjectFinder implements ObjectFinderInterface
{
    /** @var ClassMetadata  */
    protected $classMetaData;
    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var string */
    private $alias;
    /** @var QueryBuilder|null */
    private $queryBuilder;
    /** @var string[] */
    private $select = [];
    /** @var bool[] */
    private $joinStack = [];
    /** @var bool[] */
    private $groupByStack = [];
    /** @var int */
    private $parameterIndex = 0;
    /** @var int|null */
    private $offsetResult;
    /** @var int|null */
    private $maxResult;

    /**
     * ObjectManager constructor.
     * @param EntityManagerInterface $entityManager
     * @param class-string<T> $classEntityName
     * @param string|null $alias
     */
    public function __construct(EntityManagerInterface $entityManager, string $classEntityName, ?string $alias = null)
    {
        $this->entityManager = $entityManager;
        $this->classMetaData = $this->entityManager->getClassMetadata($classEntityName);
        $this->alias = $alias ?? $this->classMetaData->getTableName();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $search): ObjectFinderInterface
    {
        foreach ($search as $name => $value) {
            $this->filterField($name, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetResult(?int $offsetResult): ObjectFinderInterface
    {
        $this->offsetResult = $offsetResult;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetPageResult(int $page, int $maxResult = null): ObjectFinderInterface
    {
        if (null === $this->maxResult) {
            $this->maxResult($maxResult ?? self::DEFAULT_PAGE_SIZE);
        }

        $this->offsetResult = (abs($page - 1) * $this->maxResult);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function maxResult(?int $maxResult): ObjectFinderInterface
    {
        $this->maxResult = $maxResult;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function select(string ...$fields): ObjectFinderInterface
    {
        $this->select = [];

        foreach ($fields as $field) {
            $this->select[] = $this->getFieldName($field);
        }

        $this->queryBuilder()->select($this->select);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addSelect(string ...$fields): ObjectFinderInterface
    {
        $select = [];

        foreach ($fields as $field) {
            $selectField = $this->getFieldName($field);

            $select[] = $selectField;
            $this->select[] = $selectField;
        }

        $this->queryBuilder()->addSelect($select);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(string ...$fields): ObjectFinderInterface
    {
        foreach ($fields as $field) {
            $field = $this->getFieldName($field);

            if (isset($this->groupByStack[$field])) {
                continue;
            }

            $this->groupByStack[$field] = true;
            $this->queryBuilder()->groupBy($field);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function order($sort, string $order = null): ObjectFinderInterface
    {
        $this->queryBuilder()->orderBy($sort, $order);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function filterField(string $name, $value): ObjectFinderInterface
    {
        if (!method_exists($this, $name)) {
            $this->filterEntityFields($name, $value);
            return $this;
        }

        try {
            switch ($this->resolveType($value)) {
                case 'integer':
                case 'string':
                case 'double':
                    if (strlen($value) > 0) {
                        /** @phpstan-ignore-next-line */
                        $this->$name($value);
                    } else {
                        /** @phpstan-ignore-next-line */
                        $this->$name();
                    }

                    break;
                case 'array':
                    /** @phpstan-ignore-next-line */
                    $this->$name(...$value);
                    break;
                case 'array_assoc':
                    /** @phpstan-ignore-next-line */
                    $this->$name($value);
                    break;
                default:
                    /** @phpstan-ignore-next-line */
                    $this->$name();
            }
        } catch (TypeError $exception) {
            throw new WrongFilterValueException($name, 0, $exception);
        }

        return $this;
    }

    /**
     * @param string $field
     * @param mixed $value
     */
    protected function filterEntityFields(string $field, $value): void
    {
        if (!isset($this->classMetaData->fieldMappings[$field]) && !isset($this->classMetaData->associationMappings[$field])) {
            return;
        } else {
            $parameter = $this->getParameterName();
            $alias = $this->getAlias();

            if (is_array($value)) {
                $this->queryBuilder()
                    ->andWhere("{$alias}.{$field} IN (:{$parameter})")
                    ->setParameter($parameter, $value);
            } elseif (is_object($value) || strlen($value) > 0) {
                $this->queryBuilder()
                    ->andWhere("{$alias}.{$field} = (:{$parameter})")
                    ->setParameter($parameter, $value);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leftJoin(
        string $join,
        string $alias,
        ?string $conditionType = null,
        ?string $condition = null,
        ?string $indexBy = null
    ): ObjectFinderInterface {
        return $this->addJoin(Expr\Join::LEFT_JOIN, $join, $alias, $conditionType, $condition, $indexBy);
    }

    /**
     * {@inheritdoc}
     */
    public function join(
        string $join,
        string $alias,
        ?string $conditionType = null,
        ?string $condition = null,
        ?string $indexBy = null
    ): ObjectFinderInterface {
        return $this->addJoin(Expr\Join::INNER_JOIN, $join, $alias, $conditionType, $condition, $indexBy);
    }

    /**
     * @param string $type
     * @param string      $join          The relationship to join.
     * @param string      $alias         The alias of the join.
     * @param string|null $conditionType The condition type constant. Either ON or WITH.
     * @param string|null $condition     The condition for the join.
     * @param string|null $indexBy       The index for the join.
     * @return ObjectFinderInterface
     */
    private function addJoin(
        string $type,
        string $join,
        string $alias,
        ?string $conditionType = null,
        ?string $condition = null,
        ?string $indexBy = null
    ): ObjectFinderInterface {
        if (!isset($this->joinStack[$alias])) {
            switch ($type) {
                case Expr\Join::LEFT_JOIN:
                    $this->queryBuilder()->leftJoin($join, $alias, $conditionType, $condition, $indexBy);
                    break;
                case Expr\Join::INNER_JOIN:
                    $this->queryBuilder()->join($join, $alias, $conditionType, $condition, $indexBy);
                    break;
                default:
                    throw new LogicException('Wrong join type');
            }

            $this->joinStack[$alias] = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function where(string $condition, ...$parameters): ObjectFinderInterface
    {
        $matches = [[]];
        // Wyszukuje wszystkich bindowanych parametrów :parametr
        try {
            \Safe\preg_match_all("/:[A-Za-z0-9]+/", $condition, $matches);
        } catch (PcreException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        /** @var array<string> $matches */
        $matches = array_unique($matches[0]);

        foreach ($matches as $index => $bindName) {
            if (!array_key_exists($index, $parameters)) {
                if (null === $this->getParameter($bindName)) {
                    throw new InvalidArgumentException("Missing argument parameters with name {$bindName}");
                }

                continue;
            }

            $this->setParameter($bindName, $parameters[$index]);
        }

        /**
         * Automatyczne bindowanie aliasu do t. zostanie wycofane.
         * @deprecated
         */
        $this->queryBuilder()->andWhere(str_replace('t.', $this->getAlias() . '.', $condition));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter(string $key, $value, $type = null): ObjectFinderInterface
    {
        $this->queryBuilder()->setParameter(trim($key, ':'), $value);

        return $this;
    }

    /**
     * @param string $key
     * @return Query\Parameter|null
     */
    public function getParameter(string $key)
    {
        return $this->queryBuilder()->getParameter(trim($key, ':'));
    }

    /**
     * {@inheritdoc}
     */
    public function contextLimitation(): ObjectFinderInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws NonUniqueResultException
     */
    public function findByUuid(UuidInterface $uuid, ?int $hydration = null)
    {
        if (!isset($this->classMetaData->fieldMappings['uuid'])) {
            throw new LogicException("{$this->classMetaData->name} does not have uuid field");
        }

        return $this->findBy(['uuid' => $uuid->toString()], $hydration);
    }

    /**
     * {@inheritdoc}
     * @return T|null
     * @throws ORMException
     */
    public function find($id, $hydration = null)
    {
        if ($id instanceof UuidInterface) {
            return $this->findByUuid($id);
        }
        if (Uuid::isValid($id)) {
            return $this->findByUuid(UUid::fromString($id));
        }

        if (!is_array($id)) {
            if ($this->classMetaData->isIdentifierComposite) {
                throw ORMInvalidArgumentException::invalidCompositeIdentifier();
            }

            $id = [$this->classMetaData->identifier[0] => $id];
        }

        $sortedId = [];

        foreach ($this->classMetaData->identifier as $identifier) {
            if (!isset($id[$identifier])) {
                throw ORMException::missingIdentifierField($this->classMetaData->name, $identifier);
            }

            $sortedId[$identifier] = $id[$identifier];
            unset($id[$identifier]);
        }

        /** @phpstan-ignore-next-line */
        if ($id) {
            throw new ORMException(
                "Unrecognized identifier fields: '" . implode("', '", array_keys($id)) . "' " .
                "are not present on class '{$this->classMetaData->name}'."
            );
        }

        return $this->filter($sortedId)->query()->getOneOrNullResult($hydration);
    }

    /**
     * {@inheritdoc}
     * @return T|null
     * @throws NonUniqueResultException
     */
    public function findBy(array $search = [], $hydration = null)
    {
        return $this->filter($search)->maxResult(1)->query()->getOneOrNullResult($hydration);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $field, bool $distinct = true, array $search = []): int
    {
        $select = $this->select;
        $this->filter($search)->select($distinct ? "COUNT(DISTINCT {$field})" : "COUNT({$field})");

        foreach ($select as $selectField) {
            $this->addSelect($selectField);
        }

        // Reset groupBy, not allowed in count query.
        $this->queryBuilder()->resetDQLPart('groupBy');

        $result = $this->query()->getSingleResult();

        return (int) reset($result);
    }

    /**
     * {@inheritdoc}
     * @return T
     * @throws ORMException
     */
    public function findOrDie($id, $hydration = null)
    {
        $model = $this->find($id, $hydration);

        if (null === $model) {
            throw new NoResultException();
        }

        return $model;
    }

    /**
     * {@inheritdoc}
     * @throws ORMException
     */
    public function findOrDieAsArray($id): array
    {
        return (array) $this->findOrDie($id, AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * {@inheritdoc}
     * @return array<T>
     */
    public function findAll(array $search = [], $hydration = null): array
    {
        /** @var 1|2|3|4|5|6|string $hydration */
        $hydration = $hydration ?? AbstractQuery::HYDRATE_OBJECT;
        return $this->filter($search)->query()->getResult($hydration);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllAsArray(array $search = []): array
    {
        return $this->findAll($search, AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * {@inheritdoc}
     * @return array<T>
     */
    public function findAllDetached(array $search = [], $hydration = null): iterable
    {
        /** @var 1|2|3|4|5|6|string $hydration */
        $hydration = $hydration ?? AbstractQuery::HYDRATE_OBJECT;

        $queryBuilder = $this->filter($search)->queryBuilder();
        $limit = min($this->maxResult, 100);
        $offset = 0;

        while (true) {
            $queryBuilder->setFirstResult($offset)->setMaxResults($limit);
            $result = $queryBuilder->getQuery()->getResult($hydration);

            if (0 === count($result)) {
                break;
            }

            foreach ($result as $entity) {
                yield $entity;
                $this->entityManager->detach($entity);
            }

            $offset += $limit;

            if ($this->maxResult <= $offset) {
                break;
            }
        }

        $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function query(): Query
    {
        $this->queryBuilder()->setMaxResults($this->maxResult);

        if (null !== $this->offsetResult) {
            $this->queryBuilder()->setFirstResult($this->offsetResult);
        }

        $query = $this->queryBuilder()->getQuery();

        $this->clear();

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function queryBuilder(): QueryBuilder
    {
        if (null === $this->queryBuilder) {
            $this->queryBuilder = $this->entityManager->createQueryBuilder()
                ->from($this->classMetaData->name, $this->getAlias())
                ->select($this->getAlias());
        }

        return $this->queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return string
     */
    protected function getParameterName(): string
    {
        return  'p' . ($this->parameterIndex++);
    }

    /**
     * @param string $field
     * @return string
     */
    protected function getFieldName(string $field): string
    {
        return false === strrpos($field, '.') && isset($this->classMetaData->fieldMappings[$field])
            ? $this->getAlias() . '.' . $field
            : $field;
    }

    /**
     * Wyczyść dane po zwróconym wyniku
     */
    private function clear(): void
    {
        $this->queryBuilder = null;
        $this->joinStack =
        $this->groupByStack =
        $this->select = [];
        $this->parameterIndex = 0;
        $this->offsetResult = null;
        $this->maxResult = null;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function resolveType($value): string
    {
        $type = gettype($value);

        if ('array' === $type && array_keys($value) !== range(0, count($value) - 1)) {
            $type = 'array_assoc';
        }

        return $type;
    }

    /**
     * Klonowanie instancji
     */
    public function __clone()
    {
        if (null !== $this->queryBuilder) {
            $this->queryBuilder = clone $this->queryBuilder;
        }
    }
}
