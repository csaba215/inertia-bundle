<?php

namespace Rompetomp\InertiaBundle\Tests;

use Rompetomp\InertiaBundle\Architecture\FluentInertiaResponse;
use Rompetomp\InertiaBundle\Service\InertiaService;
use Rompetomp\InertiaBundle\Tests\Fixtures\InertiaBaseConfig;
use Rompetomp\InertiaBundle\Testing\InertiaAssertions;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InertiaServiceTest extends InertiaBaseConfig
{
    use InertiaAssertions;

    public function testSharedSingle()
    {
        $this->inertia->share('app_name', 'Testing App 1');
        $this->inertia->share('app_version', '1.0.0');
        $this->assertEquals(
            'Testing App 1',
            $this->inertia->getShared('app_name')
        );
        $this->assertEquals('1.0.0', $this->inertia->getShared('app_version'));
    }

    public function testSharedMultiple()
    {
        $this->inertia->share('app_name', 'Testing App 2');
        $this->inertia->share('app_version', '2.0.0');
        $this->assertEquals(
            [
                'app_version' => '2.0.0',
                'app_name' => 'Testing App 2',
            ],
            $this->inertia->getShared()
        );
    }

    public function testVersion()
    {
        $this->assertNull($this->inertia->getVersion());
        $this->inertia->version('1.2.3');
        $this->assertEquals('1.2.3', $this->inertia->getVersion());
    }

    public function testRootView()
    {
        $this->assertEquals(
            $this->inertiaConfig['root_view'],
            $this->inertia->getRootView()
        );
    }

    public function testSetRootView()
    {
        $this->inertia->setRootView('other-root.twig.html');
        $this->assertEquals(
            'other-root.twig.html',
            $this->inertia->getRootView()
        );
    }

    public function testSsrSettersAndGetters()
    {
        $this->assertFalse($this->inertia->isSsr());

        $this->inertia->useSsr(true);
        $this->inertia->setSsrUrl('http://localhost:13714');

        $this->assertTrue($this->inertia->isSsr());
        $this->assertSame(
            'http://localhost:13714',
            $this->inertia->getSsrUrl()
        );
    }

    public function testRenderJSON()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard');
        $this->assertInstanceOf(FluentInertiaResponse::class, $response);
        $this->assertNotEquals('Accept', $response->headers->get('Vary'));
    }

    public function testRenderProps()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard', ['test' => 123]);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['test' => 123, 'errors' => []], $data['props']);
    }

    public function testRenderSharedProps()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );
        $this->inertia->share('app_name', 'Testing App 3');
        $this->inertia->share('app_version', '2.0.0');

        $response = $this->inertia->render('Dashboard', ['test' => 123]);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(
            [
                'test' => 123,
                'app_name' => 'Testing App 3',
                'app_version' => '2.0.0',
                'errors' => [],
            ],
            $data['props']
        );
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testRenderClosureProps()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard', [
            'test' => function () {
                return 'test-value';
            },
        ]);
        $this->assertEquals(
            'test-value',
            json_decode($response->getContent(), true)['props']['test']
        );
    }

    public function testFluentResponseCanAddPropsBeforeRendering()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia
            ->render('Dashboard', ['name' => 'Ada'])
            ->with('role', 'admin')
            ->with(['team' => 'core']);

        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            [
                'name' => 'Ada',
                'role' => 'admin',
                'team' => 'core',
                'errors' => [],
            ],
            $data['props']
        );
    }

    public function testFluentResponseCanOverrideRootViewAndViewData()
    {
        $request = Request::create('https://example.test/dashboard');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);
        $this->environment
            ->shouldReceive('render')
            ->once()
            ->with(
                'inertia.twig.html',
                \Mockery::on(
                    fn(array $data) => $data['viewData'] === [
                        'title' => 'Dashboard',
                    ] && $data['page']['component'] === 'Dashboard'
                )
            )
            ->andReturn('<div>Dashboard</div>');

        $response = $this->inertia
            ->render('Dashboard')
            ->withViewData('title', 'Dashboard')
            ->rootView('inertia.twig.html');

        $this->assertSame('<div>Dashboard</div>', $response->getContent());
    }

    public function testFluentResponseCanFlashDataBeforeRendering()
    {
        $request = Request::create('https://example.test/dashboard');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia
            ->render('Dashboard')
            ->flash('notice', 'Saved');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['notice' => 'Saved'], $data['flash']);
    }

    public function testLegacyLazyPropResolvesOnlyWhenRequested()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard', [
            'lazyValue' => $this->inertia->lazy(fn() => 'loaded'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('lazyValue', $data['props']);

        $this->requestStack = \Mockery::mock(
            \Symfony\Component\HttpFoundation\RequestStack::class
        );
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'lazyValue',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'lazyValue' => $this->inertia->lazy(fn() => 'loaded'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('loaded', $data['props']['lazyValue']);
    }

    public function testLocationReturnsRedirectForNonInertiaRequests()
    {
        $request = Request::create('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->location('/login');

        $this->assertTrue($response->isRedirect('/login'));
    }

    public function testLocationReturnsInertiaConflictForInertiaRequests()
    {
        $request = Request::create('https://example.test');
        $request->headers->set('X-Inertia', true);
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->location(
            new \Symfony\Component\HttpFoundation\RedirectResponse('/login')
        );

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame(
            '/login',
            $response->headers->get('X-Inertia-Location')
        );
    }

    public function testPartialReloadSupportsOnlyExceptAndDotNotation()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' =>
                'auth.user, optionalValue, deferredValue',
            'X-Inertia-Partial-Except' => 'auth.user.password',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'auth' => [
                'user' => [
                    'name' => fn() => 'Ada',
                    'password' => 'secret',
                ],
                'notifications' => fn() => ['unread' => 5],
            ],
            'optionalValue' => $this->inertia->optional(fn() => 'optional'),
            'deferredValue' => $this->inertia->defer(fn() => 'deferred'),
            'alwaysValue' => $this->inertia->always(fn() => 'always'),
            'skipped' => fn() => 'skipped',
        ]);

        $props = json_decode($response->getContent(), true)['props'];

        $this->assertSame(
            [
                'auth' => ['user' => ['name' => 'Ada']],
                'optionalValue' => 'optional',
                'deferredValue' => 'deferred',
                'alwaysValue' => 'always',
                'errors' => [],
            ],
            $props
        );
    }

    public function testInitialResponseExcludesOptionalAndDeferredProps()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard', [
            'eager' => 'value',
            'optionalValue' => $this->inertia->optional(fn() => 'optional'),
            'deferredValue' => $this->inertia->defer(
                fn() => 'deferred',
                'sidebar'
            ),
            'alwaysValue' => $this->inertia->always(fn() => 'always'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            ['eager' => 'value', 'alwaysValue' => 'always', 'errors' => []],
            $data['props']
        );
        $this->assertSame(
            ['sidebar' => ['deferredValue']],
            $data['deferredProps']
        );
    }

    public function testDeferredPropsCanBeRescued()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'flaky',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'flaky' => $this->inertia->defer(
                fn() => throw new \RuntimeException('Failed'),
                'default',
                true
            ),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['errors' => []], $data['props']);
        $this->assertSame(['flaky'], $data['rescuedProps']);
        $this->assertSame(['default' => ['flaky']], $data['deferredProps']);
    }

    public function testMergeWrappersExposeResetAwareMetadata()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Reset' => 'resetRows',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'rows' => $this->inertia->merge(['a'], false, 'id'),
            'resetRows' => $this->inertia->merge(['b']),
            'tree' => $this->inertia->deepMerge(['children' => []]),
            'feed' => $this->inertia->scroll(['items' => []], true),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['rows'], $data['mergeProps']);
        $this->assertSame(['tree'], $data['deepMergeProps']);
        $this->assertSame(['feed'], $data['prependProps']);
        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => null,
                'currentPage' => null,
            ],
            $data['scrollProps']['feed']
        );
        $this->assertSame(['rows.id'], $data['matchPropsOn']);
        $this->assertArrayNotHasKey(
            'resetRows',
            array_flip($data['mergeProps'])
        );
    }

    public function testMergeWrapperSupportsTargetedPathsAndScrollMetadata()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard', [
            'posts' => $this->inertia
                ->merge(['data' => []])
                ->append('data', 'id'),
            'chat' => $this->inertia
                ->merge(['messages' => []])
                ->prepend('messages', 'uuid'),
            'feed' => $this->inertia
                ->scroll(['data' => []])
                ->append('data', 'id')
                ->pages(2, 1, 3),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['posts.data', 'feed.data'], $data['mergeProps']);
        $this->assertSame(['chat.messages'], $data['prependProps']);
        $this->assertSame(
            ['posts.data.id', 'chat.messages.uuid', 'feed.data.id'],
            $data['matchPropsOn']
        );
        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 1,
                'nextPage' => 3,
                'currentPage' => 2,
            ],
            $data['scrollProps']['feed']
        );
    }

    public function testPropModifiersCanBeComposedAcrossWrappers()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard', [
            'deferredRows' => $this->inertia
                ->defer(['data' => []], 'tables')
                ->merge()
                ->append('data', 'id'),
            'deferredTree' => $this->inertia
                ->defer(['children' => []])
                ->deepMerge(),
            'optionalFeed' => $this->inertia
                ->optional(['data' => []])
                ->scroll()
                ->append('data', 'id')
                ->pages(5, 4, 6),
            'alwaysRows' => $this->inertia
                ->always(['data' => []])
                ->merge()
                ->once('always-rows'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            ['alwaysRows' => ['data' => []], 'errors' => []],
            $data['props']
        );
        $this->assertSame(
            ['tables' => ['deferredRows'], 'default' => ['deferredTree']],
            $data['deferredProps']
        );
        $this->assertSame(
            ['deferredRows.data', 'optionalFeed.data', 'alwaysRows'],
            $data['mergeProps']
        );
        $this->assertSame(['deferredTree'], $data['deepMergeProps']);
        $this->assertSame(
            ['deferredRows.data.id', 'optionalFeed.data.id'],
            $data['matchPropsOn']
        );
        $this->assertSame(
            [
                'pageName' => 'page',
                'previousPage' => 4,
                'nextPage' => 6,
                'currentPage' => 5,
            ],
            $data['scrollProps']['optionalFeed']
        );
        $this->assertSame(
            ['prop' => 'alwaysRows', 'expiresAt' => null],
            $data['onceProps']['always-rows']
        );
    }

    public function testComposedDeferredScrollResolvesOnPartialReload()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'feed',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'feed' => $this->inertia
                ->defer(fn() => ['data' => ['first']])
                ->scroll()
                ->append('data'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            ['feed' => ['data' => ['first']], 'errors' => []],
            $data['props']
        );
        $this->assertSame(['feed.data'], $data['mergeProps']);
        $this->assertArrayHasKey('feed', $data['scrollProps']);
    }

    public function testOncePropsExposeMetadataAndSkipRememberedValues()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Except-Once-Props' => 'roles',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'availableRoles' => $this->inertia
                ->once(fn() => ['admin'])
                ->as('roles')
                ->until(60),
            'plans' => $this->inertia->once(fn() => ['basic'])->fresh(),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            ['plans' => ['basic'], 'errors' => []],
            $data['props']
        );
        $this->assertSame(
            'availableRoles',
            $data['onceProps']['roles']['prop']
        );
        $this->assertIsInt($data['onceProps']['roles']['expiresAt']);
        $this->assertSame(
            ['prop' => 'plans', 'expiresAt' => null],
            $data['onceProps']['plans']
        );
    }

    public function testPartialReloadAlwaysResolvesExplicitOnceProps()
    {
        $this->bootInertiaRequest([
            'X-Inertia' => true,
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'roles',
            'X-Inertia-Except-Once-Props' => 'roles',
        ]);

        $response = $this->inertia->render('Dashboard', [
            'roles' => $this->inertia->once(fn() => ['admin']),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            ['roles' => ['admin'], 'errors' => []],
            $data['props']
        );
        $this->assertSame(
            ['prop' => 'roles', 'expiresAt' => null],
            $data['onceProps']['roles']
        );
    }

    public function testSharedPropsAndHistoryFlagsAreExposed()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);
        $this->inertia->share('auth', ['user' => 'Ada']);
        $this->inertia
            ->shareOnce('countries', fn() => ['HU'])
            ->as('country-list');
        $this->inertia->encryptHistory();
        $this->inertia->clearHistory();
        $this->inertia->preserveFragment();

        $response = $this->inertia->render('Dashboard', [
            'stats' => 123,
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['auth', 'countries'], $data['sharedProps']);
        $this->assertTrue($data['encryptHistory']);
        $this->assertTrue($data['clearHistory']);
        $this->assertTrue($data['preserveFragment']);
        $this->assertSame(
            ['prop' => 'countries', 'expiresAt' => null],
            $data['onceProps']['country-list']
        );
    }

    public function testDefaultErrorsPropUsesSessionErrorsAndErrorBag()
    {
        $request = Request::create('https://example.test/form');
        $request->headers->set('X-Inertia', true);
        $request->headers->set('X-Inertia-Error-Bag', 'profile');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('errors', [
            'default' => [
                'email' => ['The email field is required.'],
            ],
        ]);
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            [
                'profile' => [
                    'email' => 'The email field is required.',
                ],
            ],
            $data['props']['errors']
        );
    }

    public function testValidationCanReturnAllErrors()
    {
        $this->bootInertiaWithConfig([
            'validation' => ['all_errors' => true],
        ]);

        $request = Request::create('https://example.test/form');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('errors', [
            'default' => [
                'email' => [
                    'The email field is required.',
                    'The email must be valid.',
                ],
            ],
        ]);
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            [
                'email' => [
                    'The email field is required.',
                    'The email must be valid.',
                ],
            ],
            $data['props']['errors']
        );
    }

    public function testFlashDataIsPulledIntoPageObject()
    {
        $request = Request::create('https://example.test/dashboard');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $this->inertia->flash('notice', 'Saved.');

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['notice' => 'Saved.'], $data['flash']);
        $this->assertFalse($request->getSession()->has('_inertia_flash'));
    }

    public function testSymfonyFlashBagIsSharedWithoutConsumingMessages()
    {
        $request = Request::create('https://example.test/dashboard');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->getFlashBag()->add('success', 'Saved.');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['success' => 'Saved.'], $data['flash']);
        $this->assertTrue(
            $request->getSession()->getFlashBag()->has('success')
        );
    }

    public function testHistoryAndFragmentFlagsSurviveThroughSession()
    {
        $request = Request::create('https://example.test/dashboard');
        $request->headers->set('X-Inertia', true);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $this->inertia->clearHistory();
        $this->inertia->preserveFragment();

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['clearHistory']);
        $this->assertTrue($data['preserveFragment']);
        $this->assertFalse(
            $request->getSession()->has('_inertia_clear_history')
        );
        $this->assertFalse(
            $request->getSession()->has('_inertia_preserve_fragment')
        );
    }

    public function testDefaultUrlIsRelativeAndPreservesTrailingSlash()
    {
        $request = Request::create('https://example.test/users/?filter=active');
        $request->headers->set('X-Inertia', true);
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('/users/?filter=active', $data['url']);
    }

    public function testUrlCanBeResolvedWithCallback()
    {
        $request = Request::create('https://example.test/users');
        $request->headers->set('X-Inertia', true);
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($request);
        $this->inertia->resolveUrlUsing(
            fn(Request $request) => '/tenant' . $request->getPathInfo()
        );

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('/tenant/users', $data['url']);
    }

    public function testConfiguredPageComponentMustExist()
    {
        $this->bootInertiaWithPageConfig();
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Admin/Users');
        $data = json_decode($response->getContent(), true);

        $this->assertSame('Admin/Users', $data['component']);
    }

    public function testMissingConfiguredPageComponentThrows()
    {
        $this->bootInertiaWithPageConfig();
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage(
            'Inertia page component "Missing/Page" does not exist'
        );

        $this->inertia->render('Missing/Page');
    }

    public function testInertiaTestingAssertions()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard', [
            'auth' => ['user' => ['name' => 'Ada']],
            'items' => [1, 2, 3],
        ]);

        $this->assertInertiaComponent($response, 'Dashboard');
        $this->assertInertiaHasProp($response, 'auth.user.name', 'Ada');
        $this->assertInertiaPropEquals($response, 'auth.user.name', 'Ada');
        $this->assertInertiaPropCount($response, 'items', 3);
        $this->assertInertiaUrl($response, null);
        $this->assertInertiaVersion($response, null);
        $this->assertInertiaMissingProp($response, 'auth.user.email');
        $this->assertInertiaComponentExists('Dashboard', [
            __DIR__ . '/fixtures/pages',
        ]);
    }

    public function testFluentInertiaTestingAssertions()
    {
        $this->bootInertiaRequest(['X-Inertia' => true]);
        $this->inertia->encryptHistory();
        $this->inertia->clearHistory();

        $response = $this->inertia->render('Dashboard', [
            'auth' => ['user' => ['name' => 'Ada']],
            'items' => [1, 2],
        ]);

        $this->assertInertia(
            $response,
            fn($page) => $page
                ->component('Dashboard')
                ->hasAll(['auth.user.name' => 'Ada', 'items'])
                ->whereAll(['auth.user.name' => 'Ada'])
                ->whereType('auth.user.name', 'string')
                ->scope(
                    'auth.user',
                    fn($user) => $user
                        ->where('name', 'Ada')
                        ->missing('email')
                        ->missingAll(['email', 'id'])
                )
                ->count('items', 2)
                ->encryptedHistory()
                ->clearsHistory()
        );
    }

    public function testDefaultVersionCanUseManifestPath()
    {
        $manifest = __DIR__ . '/fixtures/public/build/manifest.json';
        $this->bootInertiaWithConfig([
            'version' => [
                'manifest_paths' => [$manifest],
            ],
        ]);
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(hash_file('xxh128', $manifest), $data['version']);
    }

    public function testDefaultVersionCanUseAssetUrl()
    {
        $this->bootInertiaWithConfig([
            'version' => [
                'asset_url' => 'https://cdn.example.test/assets',
            ],
        ]);
        $this->bootInertiaRequest(['X-Inertia' => true]);

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(
            hash('xxh128', 'https://cdn.example.test/assets'),
            $data['version']
        );
    }

    public function testDefaultVersionAutoDiscoversCommonManifest()
    {
        $projectDir = __DIR__ . '/fixtures/auto-version';
        $manifest = $projectDir . '/public/build/manifest.json';
        $this->container = self::createContainerBuilder([
            'kernel_project_dir' => $projectDir,
            'framework' => [
                'secret' => 'testing',
                'http_method_override' => false,
            ],
            'inertia' => $this->inertiaConfig,
        ]);
        $this->container->compile();

        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);
        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(hash_file('xxh128', $manifest), $data['version']);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testRenderDoc()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => false]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->environment->allows('render')->andReturn('<div>123</div>');

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $response = $this->inertia->render('Dashboard');
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testViewDataSingle()
    {
        $this->inertia->viewData('app_name', 'Testing App 1');
        $this->inertia->viewData('app_version', '1.0.0');
        $this->assertEquals(
            'Testing App 1',
            $this->inertia->getViewData('app_name')
        );
        $this->assertEquals(
            '1.0.0',
            $this->inertia->getViewData('app_version')
        );
    }

    public function testViewDataMultiple()
    {
        $this->inertia->viewData('app_name', 'Testing App 2');
        $this->inertia->viewData('app_version', '2.0.0');
        $this->assertEquals(
            [
                'app_version' => '2.0.0',
                'app_name' => 'Testing App 2',
            ],
            $this->inertia->getViewData()
        );
    }

    public function testContextSingle()
    {
        $this->inertia->context('groups', ['group1', 'group2']);
        $this->assertEquals(
            ['group1', 'group2'],
            $this->inertia->getContext('groups')
        );
    }

    public function testContextMultiple()
    {
        $this->inertia->context('groups', ['group1', 'group2']);
        $this->assertEquals(
            [
                'groups' => ['group1', 'group2'],
            ],
            $this->inertia->getContext()
        );
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testTypesArePreservedUsingJsonEncode()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $this->innerTestTypesArePreserved(false);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testTypesArePreservedUsingSerializer()
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag(['X-Inertia' => true]));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->serializer = new Serializer(
            [new ObjectNormalizer()],
            [new JsonEncoder()]
        );
        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );

        $this->innerTestTypesArePreserved(true);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    private function innerTestTypesArePreserved($usingSerializer = false)
    {
        $props = [
            'integer' => 123,
            'float' => 1.23,
            'string' => 'test',
            'null' => null,
            'true' => true,
            'false' => false,
            'object' => new \DateTime(),
            'empty_object' => new \stdClass(),
            'iterable_object' => new \ArrayObject([1, 2, 3]),
            'empty_iterable_object' => new \ArrayObject(),
            'array' => [1, 2, 3],
            'empty_array' => [],
            'associative_array' => ['test' => 'test'],
        ];

        $response = $this->inertia->render('Dashboard', $props);
        $data = json_decode($response->getContent(), false);
        $responseProps = (array) $data->props;

        $this->assertIsInt($responseProps['integer']);
        $this->assertIsFloat($responseProps['float']);
        $this->assertIsString($responseProps['string']);
        $this->assertNull($responseProps['null']);
        $this->assertTrue($responseProps['true']);
        $this->assertFalse($responseProps['false']);
        $this->assertIsObject($responseProps['object']);
        $this->assertIsObject($responseProps['empty_object']);

        if (!$usingSerializer) {
            $this->assertIsObject($responseProps['iterable_object']);
        } else {
            $this->assertIsArray($responseProps['iterable_object']);
        }

        $this->assertIsObject($responseProps['empty_iterable_object']);
        $this->assertIsArray($responseProps['array']);
        $this->assertIsArray($responseProps['empty_array']);
        $this->assertIsObject($responseProps['associative_array']);
    }

    private function bootInertiaRequest(array $headers = []): void
    {
        $mockRequest = \Mockery::mock(Request::class);
        $mockRequest
            ->shouldReceive('getRequestUri')
            ->andSet('headers', new HeaderBag($headers));
        $mockRequest
            ->allows()
            ->getRequestUri()
            ->andReturns('https://example.test');
        $this->requestStack
            ->allows()
            ->getCurrentRequest()
            ->andReturns($mockRequest);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );
    }

    private function bootInertiaWithPageConfig(): void
    {
        $this->bootInertiaWithConfig([
            'pages' => [
                'ensure_pages_exist' => true,
                'paths' => [__DIR__ . '/fixtures/pages'],
                'extensions' => ['vue', 'tsx'],
            ],
        ]);
    }

    private function bootInertiaWithConfig(array $config): void
    {
        $this->container = self::createContainerBuilder([
            'framework' => [
                'secret' => 'testing',
                'http_method_override' => false,
            ],
            'inertia' => array_replace_recursive($this->inertiaConfig, $config),
        ]);
        $this->container->compile();

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $this->container,
            $this->serializer
        );
    }
}
