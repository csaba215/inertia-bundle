<?php

namespace Rompetomp\InertiaBundle\Tests;

use PHPUnit\Framework\TestCase;
use Rompetomp\InertiaBundle\Architecture\InertiaInterface;
use Rompetomp\InertiaBundle\Ssr\HttpGateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SsrTest extends TestCase
{
    public function testHttpGatewayDispatchesPageToConfiguredSsrUrl()
    {
        $page = [
            'component' => 'Dashboard',
            'props' => [],
            'url' => '/dashboard',
            'version' => null,
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'head' => ['<title>Dashboard</title>', '<meta name="x">'],
                'body' => '<div>SSR</div>',
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://127.0.0.1:13714/render', [
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                'body' => json_encode($page),
            ])
            ->willReturn($response);

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia
            ->expects($this->once())
            ->method('getSsrUrl')
            ->willReturn('http://127.0.0.1:13714/render');

        $ssrResponse = (new HttpGateway($httpClient, $inertia))->dispatch(
            $page
        );

        $this->assertSame(
            "<title>Dashboard</title>\n<meta name=\"x\">",
            $ssrResponse->head
        );
        $this->assertSame('<div>SSR</div>', $ssrResponse->body);
    }
}
