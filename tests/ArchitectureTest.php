<?php

namespace Rompetomp\InertiaBundle\Tests;

use PHPUnit\Framework\TestCase;
use Rompetomp\InertiaBundle\Architecture\DefaultInertiaErrorResponse;
use Rompetomp\InertiaBundle\Architecture\InertiaInterface;
use Rompetomp\InertiaBundle\Architecture\InertiaResponse;
use Rompetomp\InertiaBundle\Architecture\InertiaTrait;
use Rompetomp\InertiaBundle\Architecture\InvalidCSRFErrorResponse;
use Rompetomp\InertiaBundle\InertiaBundle;
use Rompetomp\InertiaBundle\Ssr\InertiaSsrResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ArchitectureTest extends TestCase
{
    public function testErrorResponses()
    {
        $default = (new DefaultInertiaErrorResponse())->getResponse();
        $invalidCsrf = (new InvalidCSRFErrorResponse())->getResponse();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $default->getStatusCode());
        $this->assertSame('Something went wrong with Inertia!', $default->getContent());
        $this->assertSame(Response::HTTP_FORBIDDEN, $invalidCsrf->getStatusCode());
        $this->assertSame('Invalid CSRF token.', $invalidCsrf->getContent());
    }

    public function testInertiaResponseStoresConstructorArguments()
    {
        $response = new InertiaResponse(
            component: 'Users/Index',
            props: ['users' => []],
            viewData: ['title' => 'Users'],
            context: ['groups' => ['list']],
            url: '/users'
        );

        $this->assertSame('Users/Index', $response->component);
        $this->assertSame(['users' => []], $response->props);
        $this->assertSame(['title' => 'Users'], $response->viewData);
        $this->assertSame(['groups' => ['list']], $response->context);
        $this->assertSame('/users', $response->url);
    }

    public function testSsrResponseStoresHeadAndBody()
    {
        $response = new InertiaSsrResponse('<title>Dashboard</title>', '<div>SSR</div>');

        $this->assertSame('<title>Dashboard</title>', $response->head);
        $this->assertSame('<div>SSR</div>', $response->body);
    }

    public function testInertiaTraitStoresInjectedService()
    {
        $service = $this->createMock(InertiaInterface::class);
        $consumer = new class {
            use InertiaTrait;

            public function inertia(): InertiaInterface
            {
                return $this->inertia;
            }
        };

        $consumer->setInertiaService($service);

        $this->assertSame($service, $consumer->inertia());
    }

    public function testBundleExtendsSymfonyBundle()
    {
        $this->assertInstanceOf(Bundle::class, new InertiaBundle());
    }
}
