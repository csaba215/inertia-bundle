<?php

namespace Rompetomp\InertiaBundle\Architecture;

class LazyProp extends OptionalProp
{
    /**
     * Evaluate the callback and return the result.
     *
     * @return mixed
     */
    public function __invoke(): mixed
    {
        return $this->resolve();
    }
}
