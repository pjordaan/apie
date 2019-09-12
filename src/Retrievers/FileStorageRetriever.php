<?php
namespace W2w\Lib\Apie\Retrievers;

use LimitIterator;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use W2w\Lib\Apie\Exceptions\CanNotDetermineIdException;
use W2w\Lib\Apie\Exceptions\InvalidIdException;
use W2w\Lib\Apie\Exceptions\ResourceNotFoundException;
use W2w\Lib\Apie\Persisters\ApiResourcePersisterInterface;

class FileStorageRetriever implements ApiResourcePersisterInterface, ApiResourceRetrieverInterface
{
    private $folder;

    private $propertyAccessor;

    public function __construct(string $folder, PropertyAccessor $propertyAccessor)
    {
        $this->folder = $folder;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Persist a new API resource. Should return the new API resource.
     *
     * @param mixed $resource
     * @param array $context
     * @return mixed
     */
    public function persistNew($resource, array $context = [])
    {
        $identifier = $context['identifier'] ?? 'id';
        if (!$this->propertyAccessor->isReadable($resource, $identifier)) {
            throw new CanNotDetermineIdException($resource, $identifier);
        }
        $id = $this->propertyAccessor->getValue($resource, $identifier);
        $this->store($resource, $id);

    }

    /**
     * Persist an existing API resource. The input resource is the modified API resource. Should return the new API
     * resource.
     *
     * @param $resource
     * @param $int
     * @param array $context
     * @return mixed
     */
    public function persistExisting($resource, $int, array $context = [])
    {
        $identifier = $context['identifier'] ?? 'id';
        if ($this->propertyAccessor->isReadable($resource, $identifier)) {
            $actualIdentifier = $this->propertyAccessor->getValue($resource, $identifier);
            if ((string) $actualIdentifier !== (string) $int) {
                throw new InvalidIdException((string) $int);
            }
        }
        $this->store($resource, $int);
    }

    /**
     * Removes an existing API resource.
     *
     * @param string $resourceClass
     * @param $id
     * @param array $context
     * @return mixed
     */
    public function remove(string $resourceClass, $id, array $context)
    {
        $file = $this->getFilename($resourceClass, $id);
        @unlink($file);
    }

    /**
     * Retrieves a single resource by some identifier.
     *
     * @param string $resourceClass
     * @param mixed $id
     * @param array $context
     * @return mixed
     */
    public function retrieve(string $resourceClass, $id, array $context)
    {
        $file = $this->getFilename($resourceClass, $id);
        if (!file_exists($file)) {
            throw new ResourceNotFoundException($id);
        }
        return unserialize(file_get_contents($file));
    }

    /**
     * Retrieves a list of resources with some pagination.
     *
     * @param string $resourceClass
     * @param array $context
     * @param int $pageIndex
     * @param int $numberOfItems
     * @return iterable
     */
    public function retrieveAll(string $resourceClass, array $context, int $pageIndex, int $numberOfItems): iterable
    {
        $folder = $this->getFolder($resourceClass);
        $result = [];
        $list = new LimitIterator(
            Finder::create()->files()->sortByName()->depth(0)->in($folder)->getIterator(),
            $pageIndex * $numberOfItems,
            $numberOfItems
        );
        foreach ($list as $file) {
            /** @var SplFileInfo $file */
            $result[] = $this->retrieve($resourceClass, $file->getBasename(), $context);
        }
        return $result;
    }

    protected function getFolder(string $resourceClass): string
    {
        $refl = new ReflectionClass($resourceClass);
        $folder = $this->folder . DIRECTORY_SEPARATOR . $refl->getShortName();
        if (!is_dir($folder)) {
            @mkdir($folder, 0777, true);
        }
        return $folder;
    }

    protected function getFilename(string $resourceClass, string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $id)) {
            throw new InvalidIdException($id);
        }
        $folder = $this->getFolder($resourceClass);

        return $folder . DIRECTORY_SEPARATOR . $id;

    }

    private function store($resource, string $id) {
        $filename = $this->getFilename(get_class($resource), $id);
        file_put_contents($filename, serialize($resource));
    }
}
