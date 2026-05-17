<?php

namespace Rompetomp\InertiaBundle\Architecture;

class MergeProp extends Prop
{
    public function __construct(
        mixed $value,
        bool $deep = false,
        bool $prepend = false,
        array|string|null $matchOn = null
    ) {
        parent::__construct($value);
        $this->merge($prepend, $matchOn);

        if ($deep) {
            $this->deepMerge($matchOn);
        }
    }
}
