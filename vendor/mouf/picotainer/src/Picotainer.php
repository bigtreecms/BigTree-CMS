<?php
namespace Mouf\Picotainer;

use Interop\Container\ContainerInterface as InteropContainer;
use Psr\Container\ContainerInterface as Psr11Container;

/**
 * This class is a minimalist dependency injection container.
 * It has compatibility with container-interop's ContainerInterface and delegate-lookup feature.
 *
 * @author David Négrier <david@mouf-php.com>
 */
class Picotainer implements InteropContainer
{

    /**
     * The delegate lookup.
     *
     * @var Psr11Container
     */
    protected $delegateLookupContainer;

    /**
     * The array of closures defining each entry of the container.
     *
     * @var array<string, Closure>
     */
    protected $callbacks;

    /**
     * The array of entries once they have been instantiated.
     *
     * @var array<string, mixed>
     */
    protected $objects;

    /**
     * Instantiate the container.
     *
     * @param array<string, Closure> $entries                 Entries must be passed as an array of anonymous functions.
     * @param Psr11Container         $delegateLookupContainer Optional delegate lookup container.
     */
    public function __construct(array $entries, Psr11Container $delegateLookupContainer = null)
    {
        $this->callbacks = $entries;
        $this->delegateLookupContainer = $delegateLookupContainer ?: $this;
    }

    /* (non-PHPdoc)
     * @see \Interop\Container\ContainerInterface::get()
     */
    public function get($identifier)
    {
        if (isset($this->objects[$identifier])) {
            return $this->objects[$identifier];
        }
        if (!isset($this->callbacks[$identifier])) {
            throw new PicotainerNotFoundException(sprintf('Identifier "%s" is not defined.', $identifier));
        }

        return $this->objects[$identifier] = $this->callbacks[$identifier]($this->delegateLookupContainer);
    }

    /* (non-PHPdoc)
     * @see \Interop\Container\ContainerInterface::has()
     */
    public function has($identifier)
    {
        return isset($this->callbacks[$identifier]) || isset($this->objects[$identifier]);
    }
}
