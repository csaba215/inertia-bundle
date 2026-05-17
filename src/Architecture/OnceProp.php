<?php

namespace Rompetomp\InertiaBundle\Architecture;

class OnceProp extends Prop
{
    public function __construct(mixed $value)
    {
        parent::__construct($value);
        $this->once();
    }
}
