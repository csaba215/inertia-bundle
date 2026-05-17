<?php

namespace Rompetomp\InertiaBundle\Architecture;

class DeferredProp extends Prop
{
    public function __construct(
        mixed $value,
        private string $group = 'default',
        private bool $rescue = false
    ) {
        parent::__construct($value);
        $this->defer($group, $rescue);
    }
}
