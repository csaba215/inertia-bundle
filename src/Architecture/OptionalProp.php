<?php

namespace Rompetomp\InertiaBundle\Architecture;

class OptionalProp extends Prop
{
    public function __construct(mixed $value)
    {
        parent::__construct($value);
        $this->optional();
    }
}
