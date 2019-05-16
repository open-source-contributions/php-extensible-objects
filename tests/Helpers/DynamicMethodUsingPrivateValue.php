<?php

namespace NorseBlue\ExtensibleObjects\Tests\Helpers;

use NorseBlue\ExtensibleObjects\Contracts\ExtensionMethod;

/**
 * Class DynamicMethodUsingPrivateValue
 *
 * @package NorseBlue\ExtensibleObjects\Tests\Helpers
 *
 * @extends SimpleObject
 */
class DynamicMethodUsingPrivateValue extends SimpleObject implements ExtensionMethod
{
    /**
     * @return callable(int $operand)
     */
    public function __invoke(): callable
    {
        /**
         * Add the given operand to the private value.
         *
         * @param int $operand
         *
         * @return int
         */
        return function (int $operand): int {
            return $this->private_value + $operand;
        };
    }
}
