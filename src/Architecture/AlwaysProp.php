<?php

namespace Rompetomp\InertiaBundle\Architecture;

class AlwaysProp extends Prop
{
    public function __construct(mixed $value)
    {
        parent::__construct($value);
        $this->always();
    }
}
