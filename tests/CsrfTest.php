<?php

namespace Rompetomp\InertiaBundle\Tests;

use Rompetomp\InertiaBundle\Architecture\DefaultInertiaErrorResponse;
use Rompetomp\InertiaBundle\EventListener\InertiaListener;
use Rompetomp\InertiaBundle\Tests\Fixtures\InertiaBaseConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfTest extends InertiaBaseConfig
{
    public function testCSRFInvalidTokenRequest()
    {
        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        // Create mock request:
        $request = Request::create('http://localhost/');
        $request->headers->set('X-Inertia', true);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $event->setResponse(new Response('Test Content.'));

        $listener->onKernelRequest($event);
        $this->assertEquals(
            'Invalid CSRF token.',
            $event->getResponse()->getContent()
        );
    }

    public function testCSRFValidTokenRequest()
    {
        $csrfToken = $this->createMock(CsrfTokenManagerInterface::class);

        $csrfToken
            ->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $listener = new InertiaListener(
            $this->inertia,
            $csrfToken,
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        // Create mock request:
        $request = Request::create('http://localhost/');
        $request->headers->set('X-Inertia', true);
        $request->cookies->set('X-XSRF-TOKEN', 'sadlokasds');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $event->setResponse(new Response('Test Content.'));

        $listener->onKernelRequest($event);

        $this->assertEquals(
            'Test Content.',
            $event->getResponse()->getContent()
        );
    }

    public function testCsrfTokenResponseSetCookie()
    {
        $request = Request::create('http://localhost/');
        $request->headers->set('X-Inertia', true);

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('Test Content.')
        );

        $listener->onKernelResponse($event);

        $this->assertEquals(
            'XSRF-TOKEN',
            $event
                ->getResponse()
                ->headers->getCookies(ResponseHeaderBag::COOKIES_FLAT)[0]
                ->getName()
        );
    }

    public function testResponseSetsInertiaVaryHeader()
    {
        $request = Request::create('http://localhost/');

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('Test Content.')
        );

        $listener->onKernelResponse($event);

        $this->assertEquals(
            'X-Inertia',
            $event->getResponse()->headers->get('Vary')
        );
    }

    public function testFragmentRedirectUsesInertiaRedirectHeader()
    {
        $request = Request::create('http://localhost/articles/old');
        $request->headers->set('X-Inertia', true);

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new RedirectResponse('/articles/new#section')
        );

        $listener->onKernelResponse($event);

        $this->assertEquals(
            Response::HTTP_CONFLICT,
            $event->getResponse()->getStatusCode()
        );
        $this->assertEquals(
            '/articles/new#section',
            $event->getResponse()->headers->get('X-Inertia-Redirect')
        );
    }

    public function testPrefetchFragmentRedirectIsNotConverted()
    {
        $request = Request::create('http://localhost/articles/old');
        $request->headers->set('X-Inertia', true);
        $request->headers->set('Purpose', 'prefetch');

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new RedirectResponse('/articles/new#section')
        );

        $listener->onKernelResponse($event);

        $this->assertEquals(
            Response::HTTP_FOUND,
            $event->getResponse()->getStatusCode()
        );
        $this->assertNull(
            $event->getResponse()->headers->get('X-Inertia-Redirect')
        );
    }

    public function testEmptyInertiaResponseRedirectsBack()
    {
        $request = Request::create('http://localhost/submit', 'POST');
        $request->headers->set('X-Inertia', true);
        $request->headers->set('Referer', 'http://localhost/form');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('')
        );

        $listener->onKernelResponse($event);

        $this->assertTrue($event->getResponse()->isRedirect());
        $this->assertSame(
            'http://localhost/form',
            $event->getResponse()->headers->get('Location')
        );
    }

    public function testAssetVersionMismatchReturnsConflictAndReflashes()
    {
        $request = Request::create('http://localhost/dashboard', 'GET');
        $request->headers->set('X-Inertia', true);
        $request->headers->set('X-Inertia-Version', 'old');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);
        $this->inertia->version('new');
        $this->inertia->flash('notice', 'Saved.');

        $csrfToken = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfToken
            ->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $listener = new InertiaListener(
            $this->inertia,
            $csrfToken,
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->onKernelRequest($event);

        $this->assertSame(Response::HTTP_CONFLICT, $event->getResponse()->getStatusCode());
        $this->assertSame(
            'http://localhost/dashboard',
            $event->getResponse()->headers->get('X-Inertia-Location')
        );
        $this->assertSame(
            ['notice' => 'Saved.'],
            $request->getSession()->get('_inertia_flash')
        );
    }

    public function testPutPatchDeleteRedirectsBecomeSeeOther()
    {
        $request = Request::create('http://localhost/users/1', 'PUT');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new RedirectResponse('/users')
        );

        $listener->onKernelResponse($event);

        $this->assertSame(Response::HTTP_SEE_OTHER, $event->getResponse()->getStatusCode());
    }

    public function testDebugAjaxRequestSetsToolbarReplaceHeader()
    {
        $request = Request::create('http://localhost/');
        $request->headers->set('X-Inertia', true);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            true,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('Test Content.')
        );

        $listener->onKernelResponse($event);

        $this->assertSame(
            '1',
            $event->getResponse()->headers->get('Symfony-Debug-Toolbar-Replace')
        );
    }

    public function testInternalRoutesSkipCsrfCookieGeneration()
    {
        $request = Request::create('http://localhost/_wdt');
        $request->attributes->set('_route', '_wdt');

        $listener = new InertiaListener(
            $this->inertia,
            $this->createMock(CsrfTokenManagerInterface::class),
            false,
            $this->container,
            new DefaultInertiaErrorResponse()
        );

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response('Profiler')
        );

        $listener->onKernelResponse($event);

        $this->assertCount(
            0,
            $event->getResponse()->headers->getCookies(ResponseHeaderBag::COOKIES_FLAT)
        );
    }
}
