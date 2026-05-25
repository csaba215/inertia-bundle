<?php

namespace Rompetomp\InertiaBundle\Tests;

use Rompetomp\InertiaBundle\Architecture\GatewayInterface;
use Rompetomp\InertiaBundle\Ssr\InertiaSsrResponse;
use Rompetomp\InertiaBundle\Tests\Fixtures\InertiaBaseConfig;
use Rompetomp\InertiaBundle\Twig\InertiaTwigExtension;

class InertiaTwigExtensionTest extends InertiaBaseConfig
{
    public function testInertiaFunctionRendersV3BootstrapMarkup()
    {
        $extension = new InertiaTwigExtension(
            $this->inertia,
            $this->createMock(GatewayInterface::class)
        );

        $markup = $extension->inertiaFunction([
            'component' => 'Dashboard',
            'props' => ['message' => '<hello>'],
            'url' => '/dashboard',
            'version' => '123',
        ]);

        $this->assertSame(
            '<script data-page="app" type="application/json">{"component":"Dashboard","props":{"message":"\u003Chello\u003E"},"url":"\/dashboard","version":"123"}</script><div id="app"></div>',
            (string) $markup
        );
    }

    public function testRegistersTwigFunctions()
    {
        $extension = new InertiaTwigExtension(
            $this->inertia,
            $this->createMock(GatewayInterface::class)
        );

        $functions = $extension->getFunctions();

        $this->assertSame('inertia', $functions[0]->getName());
        $this->assertSame('inertiaHead', $functions[1]->getName());
    }

    public function testInertiaFunctionUsesSsrBodyWhenEnabled()
    {
        $this->inertia->useSsr(true);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway
            ->expects($this->once())
            ->method('dispatch')
            ->with(['component' => 'Dashboard'])
            ->willReturn(
                new InertiaSsrResponse('<title>SSR</title>', '<div>SSR</div>')
            );

        $extension = new InertiaTwigExtension($this->inertia, $gateway);

        $this->assertSame(
            '<div>SSR</div>',
            (string) $extension->inertiaFunction(['component' => 'Dashboard'])
        );
    }

    public function testInertiaHeadFunctionUsesSsrHeadWhenEnabled()
    {
        $this->inertia->useSsr(true);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway
            ->expects($this->once())
            ->method('dispatch')
            ->with(['component' => 'Dashboard'])
            ->willReturn(
                new InertiaSsrResponse('<title>SSR</title>', '<div>SSR</div>')
            );

        $extension = new InertiaTwigExtension($this->inertia, $gateway);

        $this->assertSame(
            '<title>SSR</title>',
            (string) $extension->inertiaHeadFunction([
                'component' => 'Dashboard',
            ])
        );
    }

    public function testInertiaHeadFunctionReturnsEmptyMarkupWithoutSsr()
    {
        $extension = new InertiaTwigExtension(
            $this->inertia,
            $this->createMock(GatewayInterface::class)
        );

        $this->assertSame('', (string) $extension->inertiaHeadFunction([]));
    }
}
