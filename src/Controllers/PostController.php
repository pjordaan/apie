<?php

namespace W2w\Lib\Apie\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use W2w\Lib\Apie\ApiResourceFacade;
use W2w\Lib\Apie\ClassResourceConverter;

/**
 * Controller that handles a POST request to create a new instance of a resource.
 */
class PostController
{
    /**
     * @var ApiResourceFacade
     */
    private $apiResourceFacade;

    /**
     * @var ClassResourceConverter
     */
    private $converter;

    /**
     * @param ApiResourceFacade $apiResourceFacade
     * @param ClassResourceConverter $converter
     */
    public function __construct(
        ApiResourceFacade $apiResourceFacade,
        ClassResourceConverter $converter
    ) {
        $this->apiResourceFacade = $apiResourceFacade;
        $this->converter = $converter;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $resource
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, string $resource): ResponseInterface
    {
        $resourceClass = $this->converter->denormalize($resource);

        return $this->apiResourceFacade->post($resourceClass, $request)->getResponse();
    }
}