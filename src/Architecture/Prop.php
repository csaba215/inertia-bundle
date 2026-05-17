<?php

namespace Rompetomp\InertiaBundle\Architecture;

abstract class Prop
{
    /**
     * @var callable|string|array|mixed
     */
    protected mixed $value;

    protected ?string $onceKey = null;

    protected ?int $onceExpiresAt = null;

    protected bool $onceFresh = false;

    protected bool $optional = false;

    protected bool $always = false;

    protected bool $deferred = false;

    protected string $deferredGroup = 'default';

    protected bool $rescueDeferred = false;

    protected bool $merge = false;

    protected bool $deep = false;

    protected bool $prepend = false;

    protected array|string|null $matchOn = null;

    protected array $appendPaths = [];

    protected array $prependPaths = [];

    protected bool $scroll = false;

    protected string $pageName = 'page';

    protected int|string|null $previousPage = null;

    protected int|string|null $nextPage = null;

    protected int|string|null $currentPage = null;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function resolve(): mixed
    {
        if (is_callable($this->value)) {
            return call_user_func($this->value);
        }

        return $this->value;
    }

    public function once(?string $key = null): static
    {
        $this->onceKey = $key ?? '';

        return $this;
    }

    public function optional(): static
    {
        $this->optional = true;

        return $this;
    }

    public function always(): static
    {
        $this->always = true;

        return $this;
    }

    public function defer(string $group = 'default', bool $rescue = false): static
    {
        $this->deferred = true;
        $this->deferredGroup = $group;
        $this->rescueDeferred = $rescue;

        return $this;
    }

    public function merge(
        bool $prepend = false,
        array|string|null $matchOn = null
    ): static {
        $this->merge = true;
        $this->prepend = $prepend;
        $this->matchOn = $matchOn ?? $this->matchOn;

        return $this;
    }

    public function deepMerge(array|string|null $matchOn = null): static
    {
        $this->merge = true;
        $this->deep = true;
        $this->matchOn = $matchOn ?? $this->matchOn;

        return $this;
    }

    public function scroll(
        bool $prepend = false,
        array|string|null $matchOn = null
    ): static {
        $this->merge($prepend, $matchOn);
        $this->scroll = true;

        return $this;
    }

    public function append(array|string|null $paths = null, array|string|null $matchOn = null): static
    {
        $this->appendPaths = $this->normalizePaths($paths);
        $this->prepend = false;
        $this->matchOn = $matchOn ?? $this->matchOn;
        $this->merge = true;

        return $this;
    }

    public function prepend(array|string|null $paths = null, array|string|null $matchOn = null): static
    {
        $this->prependPaths = $this->normalizePaths($paths);
        $this->prepend = true;
        $this->matchOn = $matchOn ?? $this->matchOn;
        $this->merge = true;

        return $this;
    }

    public function matchOn(array|string $matchOn): static
    {
        $this->matchOn = $matchOn;
        $this->merge = true;

        return $this;
    }

    public function pageName(string $pageName): static
    {
        $this->pageName = $pageName;
        $this->scroll = true;
        $this->merge = true;

        return $this;
    }

    public function pages(
        int|string|null $currentPage = null,
        int|string|null $previousPage = null,
        int|string|null $nextPage = null
    ): static {
        $this->currentPage = $currentPage;
        $this->previousPage = $previousPage;
        $this->nextPage = $nextPage;
        $this->scroll = true;
        $this->merge = true;

        return $this;
    }

    public function as(string $key): static
    {
        $this->onceKey = $key;

        return $this;
    }

    public function fresh(bool $fresh = true): static
    {
        $this->onceFresh = $fresh;

        return $this;
    }

    public function until(\DateTimeInterface|\DateInterval|int|null $expiresAt): static
    {
        if ($expiresAt instanceof \DateTimeInterface) {
            $this->onceExpiresAt = $expiresAt->getTimestamp() * 1000;
        } elseif ($expiresAt instanceof \DateInterval) {
            $this->onceExpiresAt = (new \DateTimeImmutable())
                    ->add($expiresAt)
                    ->getTimestamp() * 1000;
        } elseif (is_int($expiresAt)) {
            $this->onceExpiresAt = (time() + $expiresAt) * 1000;
        } else {
            $this->onceExpiresAt = null;
        }

        return $this;
    }

    public function isOnce(): bool
    {
        return $this instanceof OnceProp || $this->onceKey !== null;
    }

    public function isOptional(): bool
    {
        return $this instanceof OptionalProp || $this->optional;
    }

    public function isAlways(): bool
    {
        return $this instanceof AlwaysProp || $this->always;
    }

    public function isDeferred(): bool
    {
        return $this instanceof DeferredProp || $this->deferred;
    }

    public function getGroup(): string
    {
        return $this->deferredGroup;
    }

    public function shouldRescue(): bool
    {
        return $this->rescueDeferred;
    }

    public function isMerge(): bool
    {
        return $this instanceof MergeProp || $this->merge;
    }

    public function isDeep(): bool
    {
        return $this->deep;
    }

    public function isScroll(): bool
    {
        return $this instanceof ScrollProp || $this->scroll;
    }

    public function getAppendPaths(string $basePath): array
    {
        if ($this->prepend && $this->appendPaths === []) {
            return [];
        }

        return $this->prefixPaths($basePath, $this->appendPaths ?: [null]);
    }

    public function getPrependPaths(string $basePath): array
    {
        if ($this->prependPaths !== []) {
            return $this->prefixPaths($basePath, $this->prependPaths);
        }

        return $this->prepend ? [$basePath] : [];
    }

    public function getMatchPropsOn(string $basePath): array
    {
        if (is_array($this->matchOn)) {
            $matches = [];

            foreach ($this->matchOn as $path => $matchOn) {
                if (is_int($path)) {
                    $matches[] = $this->prefixPath($basePath, (string) $matchOn);
                    continue;
                }

                $matches[] = $this->prefixPath($basePath, (string) $path) .
                    '.' .
                    $matchOn;
            }

            return $matches;
        }

        if (is_string($this->matchOn)) {
            $paths = array_merge($this->appendPaths, $this->prependPaths);

            if ($paths === []) {
                $paths = [null];
            }

            return array_map(
                fn($path) => $this->prefixPath($basePath, $path) .
                    '.' .
                    $this->matchOn,
                $paths
            );
        }

        return [];
    }

    public function getScrollMetadata(): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
        ];
    }

    public function getOnceKey(string $prop): string
    {
        return $this->onceKey ?: $prop;
    }

    public function getOnceExpiresAt(): ?int
    {
        return $this->onceExpiresAt;
    }

    public function isFresh(): bool
    {
        return $this->onceFresh;
    }

    private function normalizePaths(array|string|null $paths): array
    {
        if ($paths === null) {
            return [null];
        }

        return (array) $paths;
    }

    private function prefixPaths(string $basePath, array $paths): array
    {
        return array_map(
            fn($path) => $this->prefixPath($basePath, $path),
            $paths
        );
    }

    private function prefixPath(string $basePath, mixed $path): string
    {
        return $path === null || $path === '' ? $basePath : $basePath . '.' . $path;
    }
}
