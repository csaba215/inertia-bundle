<?php

namespace Rompetomp\InertiaBundle\Tests\Fixtures;

use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Rompetomp\InertiaBundle\DependencyInjection\InertiaExtension;
use Rompetomp\InertiaBundle\InertiaBundle;
use Rompetomp\InertiaBundle\Service\InertiaService;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;

class InertiaBaseConfig extends TestCase
{
    protected InertiaService $inertia;
    protected Container $container;
    protected LegacyMockInterface|MockInterface|Environment $environment;
    protected LegacyMockInterface|MockInterface|RequestStack $requestStack;
    protected LegacyMockInterface|MockInterface|Serializer|null $serializer;

    protected array $inertiaConfig = [
        'root_view' => 'base.twig.html',
        'ssr' => ['enabled' => false, 'url' => 'http://localhost:3000'],
        'csrf' => ['enabled' => true],
    ];

    public function setUp(): void
    {
        $container = $this->createContainerBuilder([
            'framework' => [
                'secret' => 'testing',
                'http_method_override' => false,
            ],
            'inertia' => $this->inertiaConfig,
        ]);
        $container->compile();

        $this->serializer = null;
        $this->container = $container;
        $this->environment = \Mockery::mock(Environment::class);
        $this->requestStack = \Mockery::mock(RequestStack::class);

        $this->inertia = new InertiaService(
            $this->environment,
            $this->requestStack,
            $container,
            $this->serializer
        );
    }

    protected static function createContainerBuilder(
        array $configs = []
    ): ContainerBuilder {
        $container = new ContainerBuilder(
            new ParameterBag([
                'kernel.bundles' => [
                    'FrameworkBundle' => FrameworkBundle::class,
                    'InertiaBundle' => InertiaBundle::class,
                ],
                'kernel.bundles_metadata' => [],
                'kernel.cache_dir' => __DIR__,
                'kernel.debug' => false,
                'kernel.environment' => 'test',
                'kernel.name' => 'kernel',
                'kernel.root_dir' => __DIR__,
                'kernel.project_dir' =>
                    $configs['kernel_project_dir'] ?? dirname(__DIR__, 2),
                'kernel.share_dir' => __DIR__,
                'kernel.container_class' => 'AutowiringTestContainer',
                'kernel.charset' => 'utf8',
                'kernel.runtime_environment' => 'test',
                'kernel.runtime_mode.web' => true,
                'kernel.build_dir' => __DIR__,
                'debug.file_link_format' => null,
                'env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)' => false,
                'env(default::SYMFONY_TRUSTED_HOSTS)' => '',
                'env(default::SYMFONY_TRUSTED_PROXIES)' => '',
                'env(default::SYMFONY_TRUSTED_HEADERS)' => '',
            ])
        );

        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new InertiaExtension());

        unset($configs['kernel_project_dir']);

        foreach ($configs as $extension => $config) {
            $container->loadFromExtension($extension, $config);
        }

        return $container;
    }
}
