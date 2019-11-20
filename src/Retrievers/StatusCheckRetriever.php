<?php

namespace W2w\Lib\Apie\Retrievers;

use Generator;
use LimitIterator;
use RewindableGenerator;
use W2w\Lib\Apie\ApiResources\Status;
use W2w\Lib\Apie\Exceptions\InvalidClassTypeException;
use W2w\Lib\Apie\Exceptions\ResourceNotFoundException;
use W2w\Lib\Apie\Models\ApiResourceClassMetadata;
use W2w\Lib\Apie\SearchFilters\SearchFilter;
use W2w\Lib\Apie\SearchFilters\SearchFilterRequest;
use W2w\Lib\Apie\StatusChecks\StatusCheckInterface;
use W2w\Lib\Apie\StatusChecks\StatusCheckListInterface;
use W2w\Lib\Apie\ValueObjects\PhpPrimitive;

/**
 * Status check retriever retrieves instances of Status. A status check needs to implement StatusCheckInterface
 * or StatusCheckListInterface and sent in the constructor of this method.
 */
class StatusCheckRetriever implements ApiResourceRetrieverInterface, SearchFilterProviderInterface
{
    private $statusChecks;

    /**
     * @param (StatusCheckInterface|StatusCheckListInterface)[] $statusChecks
     */
    public function __construct(iterable $statusChecks)
    {
        $this->statusChecks = $statusChecks;
    }

    /**
     * Iterates over all status checks and creates a generator for it.
     *
     * @return Generator
     */
    private function iterate(): Generator
    {
        foreach ($this->statusChecks as $statusCheck) {
            $check = false;
            if ($statusCheck instanceof StatusCheckInterface) {
                $check = true;
                yield $statusCheck->getStatus();
            }
            if ($statusCheck instanceof StatusCheckListInterface) {
                $check = true;
                foreach ($statusCheck as $check) {
                    if ($check instanceof Status) {
                        yield $check;
                    } else if ($check instanceof StatusCheckInterface) {
                        yield $check->getStatus();
                    } else {
                        throw new InvalidClassTypeException(get_class($check), 'StatusCheckInterface or Status');
                    }
                }
            }
            if (!$check) {
                throw new InvalidClassTypeException(get_class($statusCheck), 'StatusCheckInterface or StatusCheckListInterface');
            }
        }
    }

    /**
     * Finds the correct status check or throw a 404 if it could not be found.
     *
     * @param string $resourceClass
     * @param mixed $id
     * @param array $context
     * @return Status
     */
    public function retrieve(string $resourceClass, $id, array $context)
    {
        foreach ($this->iterate() as $statusCheck) {
            if ($statusCheck->getId() === $id) {
                return $statusCheck;
            }
        }
        throw new ResourceNotFoundException($id);
    }

    /**
     * Return all status check results.
     *
     * @param string $resourceClass
     * @param array $context
     * @param SearchFilterRequest $searchFilterRequest
     * @return iterable<Status>
     */
    public function retrieveAll(string $resourceClass, array $context, SearchFilterRequest $searchFilterRequest): iterable
    {
        $offset = $searchFilterRequest->getOffset();
        $numberOfItems = $searchFilterRequest->getNumberOfItems();
        return new LimitIterator(new RewindableGenerator(function () {
            return $this->iterate();
        }), $offset, $numberOfItems);
    }

    /**
     * Retrieves search filter for an api resource.
     *
     * @param ApiResourceClassMetadata $classMetadata
     * @return SearchFilter
     */
    public function getSearchFilter(ApiResourceClassMetadata $classMetadata): SearchFilter
    {
        $res = new SearchFilter();
        $res->addPrimitiveSearchFilter('status', PhpPrimitive::STRING);
        return $res;
    }
}
