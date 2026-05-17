<?php

namespace Rompetomp\InertiaBundle\Testing;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

trait InertiaAssertions
{
    protected function assertInertia(Response $response, ?callable $callback = null): array
    {
        $page = $this->inertiaPage($response);

        Assert::assertArrayHasKey('component', $page);
        Assert::assertArrayHasKey('props', $page);
        Assert::assertArrayHasKey('url', $page);
        Assert::assertArrayHasKey('version', $page);

        if ($callback !== null) {
            $callback(new AssertableInertia($page));
        }

        return $page;
    }

    protected function assertInertiaComponent(
        Response $response,
        string $component
    ): array {
        $page = $this->assertInertia($response);

        Assert::assertSame($component, $page['component']);

        return $page;
    }

    protected function assertInertiaHasProp(
        Response $response,
        string $prop,
        mixed $expected = null
    ): array {
        $page = $this->assertInertia($response);

        Assert::assertTrue(
            $this->hasInertiaPath($page['props'], $prop),
            sprintf('Failed asserting that Inertia prop "%s" exists.', $prop)
        );

        if (func_num_args() >= 3) {
            Assert::assertEquals(
                $expected,
                $this->getInertiaPath($page['props'], $prop)
            );
        }

        return $page;
    }

    protected function assertInertiaMissingProp(
        Response $response,
        string $prop
    ): array {
        $page = $this->assertInertia($response);

        Assert::assertFalse(
            $this->hasInertiaPath($page['props'], $prop),
            sprintf('Failed asserting that Inertia prop "%s" is missing.', $prop)
        );

        return $page;
    }

    protected function assertInertiaPropCount(
        Response $response,
        string $prop,
        int $count
    ): array {
        $page = $this->assertInertiaHasProp($response, $prop);
        $value = $this->getInertiaPath($page['props'], $prop);

        Assert::assertCount($count, $value);

        return $page;
    }

    protected function assertInertiaUrl(Response $response, ?string $url): array
    {
        $page = $this->assertInertia($response);

        Assert::assertSame($url, $page['url']);

        return $page;
    }

    protected function assertInertiaVersion(
        Response $response,
        string|int|null $version
    ): array {
        $page = $this->assertInertia($response);

        Assert::assertSame($version, $page['version']);

        return $page;
    }

    protected function assertInertiaEncryptedHistory(Response $response): array
    {
        $page = $this->assertInertia($response);

        Assert::assertTrue($page['encryptHistory'] ?? false);

        return $page;
    }

    protected function assertInertiaClearsHistory(Response $response): array
    {
        $page = $this->assertInertia($response);

        Assert::assertTrue($page['clearHistory'] ?? false);

        return $page;
    }

    protected function assertInertiaPropEquals(
        Response $response,
        string $prop,
        mixed $expected
    ): array {
        $page = $this->assertInertiaHasProp($response, $prop);

        Assert::assertEquals($expected, $this->getInertiaPath($page['props'], $prop));

        return $page;
    }

    protected function assertInertiaExactProps(
        Response $response,
        array $props
    ): array {
        $page = $this->assertInertia($response);

        Assert::assertEquals($props, $page['props']);

        return $page;
    }

    protected function assertInertiaComponentExists(
        string $component,
        array $paths,
        array $extensions = ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue']
    ): void {
        foreach ($paths as $path) {
            foreach ($extensions as $extension) {
                $file = rtrim($path, DIRECTORY_SEPARATOR) .
                    DIRECTORY_SEPARATOR .
                    str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $component) .
                    '.' .
                    ltrim((string) $extension, '.');

                if (is_file($file)) {
                    Assert::assertTrue(true);
                    return;
                }
            }
        }

        Assert::fail(sprintf(
            'Failed asserting that Inertia component "%s" exists.',
            $component
        ));
    }

    private function inertiaPage(Response $response): array
    {
        $content = $response->getContent();
        $page = json_decode($content, true);

        if (is_array($page)) {
            return $page;
        }

        if (
            preg_match(
                '/<script data-page="app" type="application\/json">(.*?)<\/script>/s',
                $content,
                $matches
            ) === 1
        ) {
            return json_decode(
                html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'),
                true
            );
        }

        Assert::fail('Failed asserting that response contains an Inertia page.');
    }

    private function hasInertiaPath(array $array, string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    private function getInertiaPath(array $array, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            $array = $array[$segment];
        }

        return $array;
    }
}
