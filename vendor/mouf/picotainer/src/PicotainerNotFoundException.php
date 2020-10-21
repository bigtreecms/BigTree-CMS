<?php
namespace Mouf\Picotainer;

use Interop\Container\Exception\NotFoundException;

/**
 * This exception is thrown when an identifier is passed to Picotainer and is not found.
 *
 * @author David Négrier <david@mouf-php.com>
 */
class PicotainerNotFoundException extends \InvalidArgumentException implements NotFoundException
{
}
