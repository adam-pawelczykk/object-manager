<?php
/** @author Adam Pawełczyk */

namespace ATPawelczyk\ObjectManager;

use ATPawelczyk\ObjectManager\Exception\NoResultException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;

/**
 * Management of entity objects with lots of facilities
 * @package ObjectManager
 */
class ObjectManager implements ObjectManagerInterface
{
    /** @var EntityManagerInterface */
    protected $wrapped;

    /**
     * ObjectManager constructor.
     * @param EntityManagerInterface $wrapped
     */
    public function __construct(EntityManagerInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    /**
     * {@inheritdoc}
     */
    public function getFinder(string $className, ?string $alias = null): ObjectFinderInterface
    {
        return new ObjectFinder($this->wrapped, $className, $alias);
    }

    /**
     * {@inheritdoc}
     */
    public function find($className, $id): ?object
    {
        return $this->wrapped->find($className, $id);
    }

    /**
     * @inheritDoc
     */
    public function findOrDie(string $className, $id): object
    {
        if (null === ($object = $this->find($className, $id))) {
            throw new NoResultException;
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function persist($object): void
    {
        $this->wrapped->persist($object);
    }

    /**
     * {@inheritdoc}
     */
    public function persistAll(object ...$objects): void
    {
        foreach ($objects as $object) {
            $this->persist($object);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove($object): void
    {
        $this->wrapped->remove($object);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll(object ...$objects): void
    {
        foreach ($objects as $object) {
            $this->remove($object);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->wrapped->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function detach($object): void
    {
        $this->wrapped->detach($object);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($object): void
    {
        $this->wrapped->refresh($object);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->wrapped->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository($className): \Doctrine\ORM\EntityRepository|\Doctrine\Persistence\ObjectRepository
    {
        return $this->wrapped->getRepository($className);
    }

    /**
     * {@inheritdoc}
     */
    public function getClassMetadata($className): \Doctrine\Persistence\Mapping\ClassMetadata|\Doctrine\ORM\Mapping\ClassMetadata
    {
        return $this->wrapped->getClassMetadata($className);
    }

    public function getMetadataFactory(): ClassMetadataFactory
    {
        /** @var ClassMetadataFactory<ClassMetadata<object>> $factory */
        $factory = $this->wrapped->getMetadataFactory();
        return $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeObject($obj): void
    {
        $this->wrapped->initializeObject($obj);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($object): bool
    {
        return $this->wrapped->contains($object);
    }

    /**
     * {@inheritdoc}
     */
    public function createQueryBuilder(string $className, string $alias, $indexBy = null): QueryBuilder
    {
        $classMetaData = $this->getClassMetadata($className);

        return $this->wrapped->createQueryBuilder()
            ->select($alias)
            ->from($classMetaData->getName(), $alias, $indexBy);
    }
}
