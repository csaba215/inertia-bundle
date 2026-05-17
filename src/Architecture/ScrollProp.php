<?php

namespace Rompetomp\InertiaBundle\Architecture;

class ScrollProp extends MergeProp
{
    public function __construct(
        mixed $value,
        bool $deep = false,
        bool $prepend = false,
        array|string|null $matchOn = null,
        string $pageName = 'page',
        int|string|null $previousPage = null,
        int|string|null $nextPage = null,
        int|string|null $currentPage = null
    ) {
        parent::__construct($value, $deep, $prepend, $matchOn);
        $this->scroll($prepend, $matchOn)
            ->pageName($pageName)
            ->pages($currentPage, $previousPage, $nextPage);
    }
}
