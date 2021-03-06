<?php

namespace W2w\Lib\Apie\Plugins\ApplicationInfo\ApiResources;

use W2w\Lib\Apie\Annotations\ApiResource;
use W2w\Lib\Apie\Plugins\ApplicationInfo\DataLayers\ApplicationInfoRetriever;

/**
 * Creates an application_info api resource. It's best practice to have an end point to tell what application this REST API is.
 * Other use cases are to see if multiple REST API's work in the same environment, etc.
 *
 * @ApiResource(
 *     retrieveClass=ApplicationInfoRetriever::class
 * )
 */
class ApplicationInfo
{
    /**
     * @var string
     */
    private $appName;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @param string $appName
     * @param string $environment
     * @param string $hash
     * @param bool $debug
     */
    public function __construct(string $appName, string $environment, string $hash, bool $debug)
    {
        $this->appName = $appName;
        $this->environment = $environment;
        $this->hash = $hash;
        $this->debug = $debug;
    }

    /**
     * Returns the application name.
     *
     * @return string
     */
    public function getAppName(): string
    {
        return $this->appName;
    }

    /**
     * Returns the environment, e.g. development, production, etc.
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Returns some arbitrary hash to find out which version is live, for example a git hash, composer version....
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Returns true if debug mode is on.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }
}
