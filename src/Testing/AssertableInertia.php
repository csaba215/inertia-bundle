<?php

namespace Rompetomp\InertiaBundle\Testing;

use PHPUnit\Framework\Assert;

class AssertableInertia
{
    public function __construct(private array $page, private array $scope = [])
    {
    }

    public function component(string $component): static
    {
        Assert::assertSame($component, $this->page['component']);

        return $this;
    }

    public function url(?string $url): static
    {
        Assert::assertSame($url, $this->page['url']);

        return $this;
    }

    public function version(string|int|null $version): static
    {
        Assert::assertSame($version, $this->page['version']);

        return $this;
    }

    public function has(string $prop, mixed $expected = null): static
    {
        Assert::assertTrue(
            $this->hasPath($this->props(), $prop),
            sprintf('Failed asserting that Inertia prop "%s" exists.', $prop)
        );

        if (func_num_args() >= 2) {
            Assert::assertEquals($expected, $this->getPath($this->props(), $prop));
        }

        return $this;
    }

    public function hasAll(array $props): static
    {
        foreach ($props as $key => $expected) {
            if (is_int($key)) {
                $this->has($expected);
                continue;
            }

            $this->has($key, $expected);
        }

        return $this;
    }

    public function missing(string $prop): static
    {
        Assert::assertFalse(
            $this->hasPath($this->props(), $prop),
            sprintf('Failed asserting that Inertia prop "%s" is missing.', $prop)
        );

        return $this;
    }

    public function missingAll(array $props): static
    {
        foreach ($props as $prop) {
            $this->missing($prop);
        }

        return $this;
    }

    public function where(string $prop, mixed $expected): static
    {
        return $this->has($prop, $expected);
    }

    public function whereAll(array $props): static
    {
        foreach ($props as $prop => $expected) {
            $this->where($prop, $expected);
        }

        return $this;
    }

    public function whereType(string $prop, string $type): static
    {
        $this->has($prop);
        $value = $this->getPath($this->props(), $prop);

        Assert::assertSame($type, get_debug_type($value));

        return $this;
    }

    public function count(string $prop, int $count): static
    {
        $this->has($prop);

        Assert::assertCount($count, $this->getPath($this->props(), $prop));

        return $this;
    }

    public function exact(array $props): static
    {
        Assert::assertEquals($props, $this->props());

        return $this;
    }

    public function scope(string $prop, callable $callback): static
    {
        $this->has($prop);
        $value = $this->getPath($this->props(), $prop);

        Assert::assertIsArray($value);
        $callback(new self($this->page, $value));

        return $this;
    }

    public function encryptedHistory(): static
    {
        Assert::assertTrue($this->page['encryptHistory'] ?? false);

        return $this;
    }

    public function clearsHistory(): static
    {
        Assert::assertTrue($this->page['clearHistory'] ?? false);

        return $this;
    }

    public function page(): array
    {
        return $this->page;
    }

    private function props(): array
    {
        return $this->scope ?: $this->page['props'];
    }

    private function hasPath(array $array, string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    private function getPath(array $array, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            $array = $array[$segment];
        }

        return $array;
    }
}
