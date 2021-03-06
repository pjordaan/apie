<?php
namespace W2w\Lib\Apie\Core;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use W2w\Lib\Apie\Core\Models\ApiResourceFacadeResponse;
use W2w\Lib\Apie\Core\SearchFilters\SearchFilterRequest;
use W2w\Lib\Apie\Events\DeleteResourceEvent;
use W2w\Lib\Apie\Events\ModifySingleResourceEvent;
use W2w\Lib\Apie\Events\RetrievePaginatedResourcesEvent;
use W2w\Lib\Apie\Events\RetrieveSingleResourceEvent;
use W2w\Lib\Apie\Events\StoreExistingResourceEvent;
use W2w\Lib\Apie\Events\StoreNewResourceEvent;
use W2w\Lib\Apie\Exceptions\MethodNotAllowedException;
use W2w\Lib\Apie\Interfaces\FormatRetrieverInterface;
use W2w\Lib\Apie\Interfaces\ResourceSerializerInterface;
use W2w\Lib\Apie\OpenApiSchema\SubActions\SubAction;
use W2w\Lib\Apie\OpenApiSchema\SubActions\SubActionContainer;
use W2w\Lib\Apie\OpenApiSchema\SubActions\SubActionFactory;
use W2w\Lib\Apie\PluginInterfaces\ResourceLifeCycleInterface;
use W2w\Lib\Apie\Plugins\Core\Serializers\SymfonySerializerAdapter;

class ApiResourceFacade
{
    /**
     * @var ApiResourceRetriever
     */
    private $retriever;

    /**
     * @var ApiResourcePersister
     */
    private $persister;

    /**
     * @var ClassResourceConverter
     */
    private $converter;

    /**
     * @var ResourceSerializerInterface
     */
    private $serializer;

    /**
     * @var FormatRetrieverInterface
     */
    private $formatRetriever;

    /**
     * @var ResourceLifeCycleInterface[]
     */
    private $resourceLifeCycles;

    /**
     * @var SubActionContainer
     */
    private $subActionContainer;

    /**
     * @var NameConverterInterface
     */
    private $nameConverter;

    /**
     * @var ApiResourceFacadeResponseFactory
     */
    private $responseFactory;

    public function __construct(
        ApiResourceRetriever $retriever,
        ApiResourcePersister $persister,
        ClassResourceConverter $converter,
        ResourceSerializerInterface $serializer,
        FormatRetrieverInterface $formatRetriever,
        SubActionContainer $subActionContainer,
        NameConverterInterface $nameConverter,
        ApiResourceFacadeResponseFactory  $responseFactory,
        iterable $resourceLifeCycles
    ) {
        $this->retriever = $retriever;
        $this->persister = $persister;
        $this->converter = $converter;
        $this->serializer = $serializer;
        $this->formatRetriever = $formatRetriever;
        $this->subActionContainer = $subActionContainer;
        $this->nameConverter = $nameConverter;
        $this->responseFactory = $responseFactory;
        $this->resourceLifeCycles = $resourceLifeCycles;
    }

    private function runLifeCycleEvent(string $event, ...$args)
    {
        foreach ($this->resourceLifeCycles as $resourceLifeCycle) {
            $resourceLifeCycle->$event(...$args);
        }
    }

    /**
     * Does a DELETE instance call.
     *
     * @param string $resourceClass
     * @param string $id
     * @return ApiResourceFacadeResponse
     */
    public function delete(string $resourceClass, string $id): ApiResourceFacadeResponse
    {
        $event = new DeleteResourceEvent($resourceClass, $id);
        $this->runLifeCycleEvent('onPreDeleteResource', $event);
        $this->persister->delete($resourceClass, $id);
        $this->runLifeCycleEvent('onPostDeleteResource', $event);

        return new ApiResourceFacadeResponse(
            $this->serializer,
            null,
            'application/json'
        );
    }

    /**
     * Does a GET instance call.
     *
     * @param string $resourceClass
     * @param string $id
     * @param RequestInterface|null $request
     * @return ApiResourceFacadeResponse
     */
    public function get(string $resourceClass, string $id, ?RequestInterface $request): ApiResourceFacadeResponse
    {
        $event = new RetrieveSingleResourceEvent($resourceClass, $id, $request);
        $this->runLifeCycleEvent('onPreRetrieveResource', $event);
        // preRetrieveResource event could override resource...
        if (!$event->getResource()) {
            $event->setResource($this->retriever->retrieve($resourceClass, $id));
        }
        $this->runLifeCycleEvent('onPostRetrieveResource', $event);

        return $this->responseFactory->createResponseForResource($event->getResource(), $request);
    }

    /**
     * Does a GET all call.
     *
     * @param string $resourceClass
     * @param ServerRequestInterface|null $request
     * @return ApiResourceFacadeResponse
     */
    public function getAll(string $resourceClass, ?ServerRequestInterface $request): ApiResourceFacadeResponse
    {
        $searchFilterRequest = new SearchFilterRequest();
        if ($request) {
            $searchFilterRequest = SearchFilterRequest::createFromPsrRequest($request);
        }
        $event = new RetrievePaginatedResourcesEvent($resourceClass, $searchFilterRequest, $request);
        $this->runLifeCycleEvent('onPreRetrieveAllResources', $event);
        if (null === $event->getResources()) {
            $event->setResources($this->retriever->retrieveAll($resourceClass, $searchFilterRequest));
        }
        $this->runLifeCycleEvent('onPostRetrieveAllResources', $event);

        return $this->responseFactory->createResponseListForResource($event->getResources(), $resourceClass, $searchFilterRequest, $request);
    }

    /**
     * Does a PUT instance call.
     *
     * @param string $resourceClass
     * @param string $id
     * @param RequestInterface $request
     * @return ApiResourceFacadeResponse
     */
    public function put(string $resourceClass, string $id, RequestInterface $request): ApiResourceFacadeResponse
    {
        $resource = $this->get($resourceClass, $id, $request)->getResource();
        $event = new ModifySingleResourceEvent($resource, $id, $request);
        $this->runLifeCycleEvent('onPreModifyResource', $event);
        $request = $event->getRequest();

        $event->setResource(
            $this->serializer->putData(
                $event->getResource(),
                (string) $request->getBody(),
                $request->getHeader('Content-Type')[0] ?? 'application/json'
            )
        );
        $this->runLifeCycleEvent('onPostModifyResource', $event);

        $event = new StoreExistingResourceEvent($event);
        $this->runLifeCycleEvent('onPrePersistExistingResource', $event);
        $event->setResource($this->persister->persistExisting($event->getResource(), $id));
        $this->runLifeCycleEvent('onPostPersistExistingResource', $event);

        return $this->responseFactory->createResponseForResource($event->getResource(), $request);
    }

    /**
     * Does a POST new instance call.
     *
     * @param string $resourceClass
     * @param RequestInterface $request
     * @return ApiResourceFacadeResponse
     */
    public function post(string $resourceClass, RequestInterface $request): ApiResourceFacadeResponse
    {
        $event = new StoreNewResourceEvent($resourceClass, $request);
        $this->runLifeCycleEvent('onPreCreateResource', $event);
        if (!$event->getResource()) {
            $event->setResource($this->serializer->postData(
                $resourceClass,
                (string)$event->getRequest()->getBody(),
                $event->getRequest()->getHeader('Content-Type')[0] ?? 'application/json'
            ));
        }
        $this->runLifeCycleEvent('onPostCreateResource', $event);
        $event = new StoreExistingResourceEvent($event);
        $this->runLifeCycleEvent('onPrePersistNewResource', $event);
        $event->setResource($this->persister->persistNew($event->getResource()));
        $this->runLifeCycleEvent('onPostPersistNewResource', $event);

        return $this->responseFactory->createResponseForResource($event->getResource(), $request);
    }

    /**
     * Runs a sub action.
     * @param string $resourceClass
     * @param string $id
     * @param string $actionName
     * @param RequestInterface $request
     * @return ApiResourceFacadeResponse
     * @todo move logic to SubActionContainer
     */
    public function postSubAction(string $resourceClass, string $id, string $actionName, RequestInterface $request): ApiResourceFacadeResponse
    {
        $subActions = $this->subActionContainer->getSubActionsForResourceClass($resourceClass);
        if (empty($subActions[$actionName])) {
            throw new MethodNotAllowedException('POST');
        }
        /** @var SubAction $subAction */
        $subAction = $subActions[$actionName];
        $resource = $this->get($resourceClass, $id, $request)->getResource();
        $reflectionMethod = $subAction->getReflectionMethod();
        $context = [
            'initial-arguments' => [],
            'object-instance' => $subAction->getObject(),
        ];
        assert($this->serializer instanceof SymfonySerializerAdapter);
        $parameters = $reflectionMethod->getParameters();
        if ($parameters) {
            $context['initial-arguments'][$this->nameConverter->normalize($parameters[0]->getName())] = $resource;
        }
        $data = $this->serializer->getSerializer()->deserialize(
            $request->getBody(),
            'ReflectionMethod::' . get_class($subAction->getObject()) . '::' . $reflectionMethod->getName(),
            'json',
            $context
        );
        return $this->responseFactory->createResponseForResource($data, $request);
    }
}
