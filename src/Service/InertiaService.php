<?php

namespace Rompetomp\InertiaBundle\Service;

use Closure;
use Rompetomp\InertiaBundle\Architecture\AlwaysProp;
use Rompetomp\InertiaBundle\Architecture\DeferredProp;
use Rompetomp\InertiaBundle\Architecture\FluentInertiaResponse;
use Rompetomp\InertiaBundle\Architecture\InertiaInterface;
use Rompetomp\InertiaBundle\Architecture\LazyProp;
use Rompetomp\InertiaBundle\Architecture\MergeProp;
use Rompetomp\InertiaBundle\Architecture\OnceProp;
use Rompetomp\InertiaBundle\Architecture\OptionalProp;
use Rompetomp\InertiaBundle\Architecture\Prop;
use Rompetomp\InertiaBundle\Architecture\ScrollProp;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * The class that provides the Inertia service to the application.
 */
class InertiaService implements InertiaInterface
{
    private const FLASH_SESSION_KEY = '_inertia_flash';

    private const CLEAR_HISTORY_SESSION_KEY = '_inertia_clear_history';

    private const PRESERVE_FRAGMENT_SESSION_KEY = '_inertia_preserve_fragment';

    private object $missingProp;

    protected array $sharedProps = [];

    protected array $sharedOnceProps = [];

    protected array $sharedViewData = [];

    protected array $sharedContext = [];

    protected array $flashData = [];

    protected ?string $version = null;

    protected bool $useSsr = false;

    protected string $ssrUrl = '';

    protected ?string $rootView = null;

    protected bool $encryptHistory = false;

    protected bool $clearHistory = false;

    protected bool $preserveFragment = false;

    protected mixed $urlResolver = null;

    public function __construct(
        protected Environment $engine,
        protected RequestStack $requestStack,
        private ContainerInterface $container,
        protected ?SerializerInterface $serializer = null
    ) {
        $this->missingProp = new \stdClass();

        /**
         * Check if SSR is enabled and set the SSR URL.
         */
        if (
            $this->container->hasParameter('inertia.ssr.enabled') &&
            $this->container->getParameter('inertia.ssr.enabled')
        ) {
            $this->useSsr(true);
            $this->setSsrUrl($this->container->getParameter('inertia.ssr.url'));
        }

        /**
         * Set the root view if it is set in the configuration.
         */
        if ($this->container->hasParameter('inertia.root_view')) {
            $this->setRootView(
                $this->container->getParameter('inertia.root_view')
            );
        }

        if ($this->container->hasParameter('inertia.history.encrypt')) {
            $this->encryptHistory(
                (bool) $this->container->getParameter('inertia.history.encrypt')
            );
        }

        if ($this->version === null) {
            $this->version = $this->resolveDefaultVersion();
        }
    }

    /**
     * Adds global component properties for the templating system.
     * @param string $key
     * @param mixed|null $value
     * @return void
     */
    public function share(string $key, mixed $value = null): void
    {
        $this->sharedProps[$key] = $value;
    }

    /**
     * Get global component properties by key.
     * @param string|null $key
     * @return mixed
     */
    public function getShared(?string $key = null): mixed
    {
        if ($key) {
            return $this->sharedProps[$key] ?? null;
        }

        return $this->sharedProps;
    }

    public function shareOnce(string $key, mixed $value = null): OnceProp
    {
        $prop = $this->once($value);
        $this->sharedOnceProps[$key] = $prop;

        return $prop;
    }

    /**
     * Set additional view data for the templating system.
     *
     * @param string $key
     * @param mixed|null $value
     * @return void
     */
    public function viewData(string $key, mixed $value = null): void
    {
        $this->sharedViewData[$key] = $value;
    }

    /**
     * Get the data that should be passed to the view.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getViewData(?string $key = null): mixed
    {
        if ($key) {
            return $this->sharedViewData[$key] ?? null;
        }

        return $this->sharedViewData;
    }

    /**
     * Set the context for the serializer.
     *
     * @param string $key
     * @param mixed|null $value
     * @return void
     */
    public function context(string $key, mixed $value = null): void
    {
        $this->sharedContext[$key] = $value;
    }

    /**
     * Get the context by key.
     * @param string|null $key
     * @return mixed
     */
    public function getContext(?string $key = null): mixed
    {
        if ($key) {
            return $this->sharedContext[$key] ?? null;
        }

        return $this->sharedContext;
    }

    /**
     * Set the version of the application.
     * @param string $version
     * @return void
     */
    public function version(string $version): void
    {
        $this->version = $version;
    }

    /**
     * Get the version of the application.
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Set the root view.
     * @param string $rootView
     * @return void
     */
    public function setRootView(string $rootView): void
    {
        $this->rootView = $rootView;
    }

    /**
     * Get the root view.
     * @return string|null
     */
    public function getRootView(): ?string
    {
        return $this->rootView;
    }

    /**
     * Set if it uses ssr.
     * @param bool $useSsr
     * @return void
     */
    public function useSsr(bool $useSsr): void
    {
        $this->useSsr = $useSsr;
    }

    /**
     * Check if it's using ssr.
     * @return bool
     */
    public function isSsr(): bool
    {
        return $this->useSsr;
    }

    /**
     * Set the ssr url where it will fetch its content.
     * @param string $url
     * @return void
     */
    public function setSsrUrl(string $url): void
    {
        $this->ssrUrl = $url;
    }

    /**
     * Get the ssr url where it will fetch its content.
     * @return string
     */
    public function getSsrUrl(): string
    {
        return $this->ssrUrl;
    }

    /**
     * Function that makes your controller return an Inertia response.
     *
     * @param string $component The component to render. Can be a path, but it must be relative to the pages dir that you are importing inside your frontend.
     * @param array $props The props to pass to the component.
     * @param array $viewData The view data to pass to the root view (ak. your twig template).
     * @param array $context The context to pass to the serializer.
     * @param string|null $url The URL to pass to the page.
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(
        string $component,
        array $props = [],
        array $viewData = [],
        array $context = [],
        ?string $url = null
    ): Response {
        $this->ensureComponentExists($component);

        return new FluentInertiaResponse(
            $this,
            $component,
            $props,
            $viewData,
            $context,
            $url
        );
    }

    /**
     * @internal
     */
    public function renderResponse(
        string $component,
        array $props = [],
        array $viewData = [],
        array $context = [],
        ?string $url = null,
        ?string $rootView = null
    ): Response {
        $rootView = $rootView ?? $this->rootView;

        /**
         * If the root view is not set, throw an exception.
         */
        if ($rootView === null) {
            throw new RuntimeError(
                'The root view is not set. Inertia bundle requires a root view to render the page, set one globally in config/packages/inertia.yaml or pass it to the render method.'
            );
        }

        $context = array_merge($this->sharedContext, $context);
        $viewData = array_merge($this->sharedViewData, $viewData);
        $props = array_merge(
            $this->sharedProps,
            $this->sharedOnceProps,
            $props
        );
        $request = $this->requestStack->getCurrentRequest();
        $this->ensureComponentExists($component);
        $props = $this->withDefaultErrorsProp($props, $request);
        $url = $url ?? $this->resolveUrl($request);

        if ($url === '') {
            $url = null;
        }

        $deferredProps = $this->resolveDeferredProps($props);
        $mergeMetadata = $this->resolveMergeMetadata($props);
        $onceProps = $this->resolveOnceProps($props);
        $props = $this->filterProps($props, $component);
        $rescuedProps = [];
        $props = $this->resolveProps($props, $rescuedProps);

        $version = $this->version;

        /**
         * Serialize the page props.
         */
        $page = compact('component', 'props', 'url', 'version');
        $page = array_merge($page, $mergeMetadata);

        if ($this->encryptHistory) {
            $page['encryptHistory'] = true;
        }

        if ($this->shouldClearHistory($request)) {
            $page['clearHistory'] = true;
        }

        if ($this->shouldPreserveFragment($request)) {
            $page['preserveFragment'] = true;
        }

        $flash = $this->pullFlashData($request);

        if ($flash !== []) {
            $page['flash'] = $flash;
        }

        $sharedProps = $this->resolveSharedProps();

        if ($sharedProps !== []) {
            $page['sharedProps'] = $sharedProps;
        }

        if ($deferredProps !== []) {
            $page['deferredProps'] = $deferredProps;
        }

        if ($rescuedProps !== []) {
            $page['rescuedProps'] = $rescuedProps;
        }

        if ($onceProps !== []) {
            $page['onceProps'] = $onceProps;
        }

        $page = $this->serialize($page, $context);

        /**
         * If the request is an Inertia request, we return a JSON response.
         */
        if ($request->headers->get('X-Inertia')) {
            return new JsonResponse($page, Response::HTTP_OK, [
                'X-Inertia' => true,
            ]);
        }

        /**
         * Update the Response content to use the root view, pass the props and render the page.
         */
        $response = new Response();

        $response->setContent(
            $this->engine->render($rootView, compact('page', 'viewData'))
        );

        return $response;
    }

    /**
     * Function to redirect users from the backend to a non inertia page.
     *
     * @param string|RedirectResponse $url
     * @return Response
     */
    public function location(string|RedirectResponse $url): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($url instanceof RedirectResponse) {
            $url = $url->getTargetUrl();
        }

        if ($request->headers->has('X-Inertia')) {
            return new Response('', Response::HTTP_CONFLICT, [
                'X-Inertia-Location' => $url,
            ]);
        }

        return new RedirectResponse($url);
    }

    /**
     * Lazy load a prop. This is useful when you want to load a prop only when it is needed.
     *
     * NEVER included on first visit...
     * OPTIONALLY included on partial reloads...
     * ONLY evaluated when needed...
     *
     * @see https://inertiajs.com/partial-reloads#lazy-data-evaluation
     *
     * @param callable|array|string $callback
     * @return LazyProp
     */
    public function lazy(callable|array|string $callback): LazyProp
    {
        return new LazyProp($this->normalizeCallback($callback));
    }

    public function optional(mixed $value): OptionalProp
    {
        return new OptionalProp($this->normalizeCallback($value));
    }

    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($this->normalizeCallback($value));
    }

    public function defer(
        mixed $value,
        string $group = 'default',
        bool $rescue = false
    ): DeferredProp {
        return new DeferredProp(
            $this->normalizeCallback($value),
            $group,
            $rescue
        );
    }

    public function merge(
        mixed $value,
        bool $prepend = false,
        array|string|null $matchOn = null
    ): MergeProp {
        return new MergeProp(
            $this->normalizeCallback($value),
            false,
            $prepend,
            $matchOn
        );
    }

    public function deepMerge(
        mixed $value,
        array|string|null $matchOn = null
    ): MergeProp {
        return new MergeProp(
            $this->normalizeCallback($value),
            true,
            false,
            $matchOn
        );
    }

    public function scroll(
        mixed $value,
        bool $prepend = false,
        array|string|null $matchOn = null
    ): ScrollProp {
        return new ScrollProp(
            $this->normalizeCallback($value),
            false,
            $prepend,
            $matchOn
        );
    }

    public function once(mixed $value): OnceProp
    {
        return new OnceProp($this->normalizeCallback($value));
    }

    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    public function clearHistory(bool $clear = true): void
    {
        $this->clearHistory = $clear;

        if ($clear) {
            $this->storeSessionFlag(self::CLEAR_HISTORY_SESSION_KEY);
        }
    }

    public function preserveFragment(bool $preserve = true): void
    {
        $this->preserveFragment = $preserve;

        if ($preserve) {
            $this->storeSessionFlag(self::PRESERVE_FRAGMENT_SESSION_KEY);
        }
    }

    public function resolveUrlUsing(?callable $resolver): void
    {
        $this->urlResolver = $resolver;
    }

    public function flash(string|array $key, mixed $value = null): void
    {
        $data = is_array($key) ? $key : [$key => $value];
        $this->flashData = array_replace_recursive($this->flashData, $data);

        $session = $this->currentSession();

        if ($session === null) {
            return;
        }

        $session->set(
            self::FLASH_SESSION_KEY,
            array_replace_recursive(
                (array) $session->get(self::FLASH_SESSION_KEY, []),
                $data
            )
        );
    }

    public function reflash(?Request $request = null): void
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();
        $session = $this->currentSession($request);

        if ($session === null) {
            return;
        }

        $flash = array_replace_recursive(
            (array) $session->get(self::FLASH_SESSION_KEY, []),
            $this->flashData
        );

        if ($flash !== []) {
            $session->set(self::FLASH_SESSION_KEY, $flash);
        }
    }

    private function normalizeCallback(mixed $callback): mixed
    {
        if (is_string($callback) && $this->container->has($callback)) {
            return $this->container->get($callback);
        }

        /**
         * If the callback is a static callable string we transform it into an array.
         */
        if (is_string($callback) && str_contains($callback, '::')) {
            $callback = explode('::', $callback, 2);
        }

        /**
         * If the callback is an array, we check if the first element is a service in the container. If it is, we return a LazyProp with the service.
         */
        if (is_array($callback) && array_key_exists(0, $callback)) {
            [$name, $action] = array_pad(array_values($callback), 2, null);
            $useContainer = is_string($name) && $this->container->has($name);
            /**
             * A service is found in the container and an action is provided, we return a LazyProp with the service and the action.
             */
            if ($useContainer && !is_null($action)) {
                return [$this->container->get($name), $action];
            }

            /**
             * A service is found in the container and an action is NOT provided, we return a LazyProp with the service and without the action.
             */
            if ($useContainer && is_null($action)) {
                return $this->container->get($name);
            }
        }

        /**
         * If the callback is a string, and it is not a service in the container, we return a LazyProp with the string and action.
         */
        return $callback;
    }

    /**
     * Serializes the given objects with the given context if the Symfony Serializer is available. If not, use `json_encode`.
     *
     * @see https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/AJAX_Security_Cheat_Sheet.md#always-return-json-with-an-object-on-the-outside
     *
     * @param array $page
     * @param array $context
     *
     * @return array returns a decoded array of the previously JSON-encoded data, so it can safely be given to {@see JsonResponse}
     */
    private function serialize(array $page, array $context = []): array
    {
        if (null !== $this->serializer) {
            $json = $this->serializer->serialize(
                $page,
                'json',
                array_merge(
                    [
                        'json_encode_options' =>
                            JsonResponse::DEFAULT_ENCODING_OPTIONS,
                        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function () {
                            return null;
                        },
                        AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
                        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
                    ],
                    $context
                )
            );
        } else {
            $json = json_encode($page);
        }

        return (array) json_decode($json, false);
    }

    private function withDefaultErrorsProp(
        array $props,
        Request $request
    ): array {
        if (array_key_exists('errors', $props)) {
            return $props;
        }

        $props['errors'] = $this->always(
            fn() => $this->resolveValidationErrors($request)
        );

        return $props;
    }

    private function resolveValidationErrors(Request $request): object|array
    {
        $errors = [];
        $session = $this->currentSession($request);

        if ($session !== null && $session->has('errors')) {
            $errors = $this->normalizeErrors($session->get('errors'));
        } elseif (
            $session !== null &&
            method_exists($session, 'getFlashBag') &&
            $session->getFlashBag()->has('errors')
        ) {
            $errors = $this->normalizeErrors(
                $session->getFlashBag()->peek('errors', [])
            );
        }

        if (!is_array($errors) || $errors === []) {
            return new \stdClass();
        }

        $bag = $request->headers->get('X-Inertia-Error-Bag');

        if ($bag !== null && array_key_exists('default', $errors)) {
            return [$bag => $errors['default']];
        }

        if (array_key_exists('default', $errors) && count($errors) === 1) {
            return $errors['default'];
        }

        return $errors;
    }

    private function normalizeErrors(mixed $errors): array
    {
        if (is_object($errors) && method_exists($errors, 'getBags')) {
            $errors = $errors->getBags();
        }

        if (is_object($errors)) {
            $errors = get_object_vars($errors);
        }

        if (!is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $key => $value) {
            if (is_object($value) && method_exists($value, 'messages')) {
                $value = $value->messages();
            } elseif (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (is_array($value)) {
                $normalized[$key] = $this->validationMessages($value);
            }
        }

        return $normalized;
    }

    private function validationMessages(array $messages): array
    {
        if (
            $this->container->hasParameter('inertia.validation.all_errors') &&
            $this->container->getParameter('inertia.validation.all_errors')
        ) {
            return $messages;
        }

        $normalized = [];

        foreach ($messages as $field => $errors) {
            if (is_array($errors)) {
                $normalized[$field] = reset($errors) ?: null;
                continue;
            }

            $normalized[$field] = $errors;
        }

        return $normalized;
    }

    private function resolveDefaultVersion(): ?string
    {
        if (
            $this->container->hasParameter('inertia.version.asset_url') &&
            $this->container->getParameter('inertia.version.asset_url')
        ) {
            return hash(
                'xxh128',
                (string) $this->container->getParameter(
                    'inertia.version.asset_url'
                )
            );
        }

        foreach (
            $this->getArrayParameter('inertia.version.manifest_paths')
            as $path
        ) {
            if (is_file($path)) {
                return hash_file('xxh128', $path);
            }
        }

        foreach ($this->defaultManifestPaths() as $path) {
            if (is_file($path)) {
                return hash_file('xxh128', $path);
            }
        }

        return null;
    }

    private function defaultManifestPaths(): array
    {
        $projectDir = $this->container->hasParameter('kernel.project_dir')
            ? (string) $this->container->getParameter('kernel.project_dir')
            : getcwd();

        return [
            $projectDir . '/public/build/manifest.json',
            $projectDir . '/public/hot',
            $projectDir . '/public/mix-manifest.json',
        ];
    }

    private function pullFlashData(Request $request): array
    {
        $session = $this->currentSession($request);
        $flash = $this->flashData;
        $this->flashData = [];

        if ($session === null) {
            return $flash;
        }

        $sessionFlash = (array) $session->get(self::FLASH_SESSION_KEY, []);
        $session->remove(self::FLASH_SESSION_KEY);
        $flash = array_replace_recursive($sessionFlash, $flash);

        if (method_exists($session, 'getFlashBag')) {
            $flashBag = $session->getFlashBag();
            $names = method_exists($flashBag, 'keys')
                ? $flashBag->keys()
                : array_keys($flashBag->peekAll());

            foreach ($names as $key) {
                if ($key === 'errors') {
                    continue;
                }

                $messages = method_exists($flashBag, 'peek')
                    ? $flashBag->peek($key, [])
                    : $flashBag->peekAll()[$key] ?? [];

                $flash[$key] =
                    count($messages) === 1 ? reset($messages) : $messages;
            }
        }

        return $flash;
    }

    private function shouldClearHistory(Request $request): bool
    {
        return $this->pullSessionFlag(
            $request,
            self::CLEAR_HISTORY_SESSION_KEY
        ) || $this->clearHistory;
    }

    private function shouldPreserveFragment(Request $request): bool
    {
        return $this->pullSessionFlag(
            $request,
            self::PRESERVE_FRAGMENT_SESSION_KEY
        ) || $this->preserveFragment;
    }

    private function storeSessionFlag(string $key): void
    {
        $session = $this->currentSession();

        if ($session !== null) {
            $session->set($key, true);
        }
    }

    private function pullSessionFlag(Request $request, string $key): bool
    {
        $session = $this->currentSession($request);

        if ($session === null) {
            return false;
        }

        $value = (bool) $session->get($key, false);
        $session->remove($key);

        return $value;
    }

    private function currentSession(?Request $request = null): mixed
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        try {
            if (!$request->hasSession()) {
                return null;
            }

            return $request->getSession();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUrl(Request $request): string
    {
        if (is_callable($this->urlResolver)) {
            return call_user_func($this->urlResolver, $request);
        }

        $url = $request->getRequestUri();

        if ($url === '') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY);
        $rawPath = parse_url($request->getRequestUri(), PHP_URL_PATH) ?: '';

        if (
            $rawPath !== '' &&
            str_ends_with($rawPath, '/') &&
            !str_ends_with($path, '/')
        ) {
            $path .= '/';
        }

        return $query === null ? $path : $path . '?' . $query;
    }

    private function ensureComponentExists(string $component): void
    {
        if (
            !$this->container->hasParameter(
                'inertia.pages.ensure_pages_exist'
            ) ||
            !$this->container->getParameter('inertia.pages.ensure_pages_exist')
        ) {
            return;
        }

        if ($this->componentExists($component)) {
            return;
        }

        throw new RuntimeError(
            sprintf(
                'Inertia page component "%s" does not exist in configured paths.',
                $component
            )
        );
    }

    private function componentExists(string $component): bool
    {
        $paths = $this->getArrayParameter('inertia.pages.paths');
        $extensions = $this->getArrayParameter('inertia.pages.extensions');

        foreach ($paths as $path) {
            foreach ($extensions as $extension) {
                $file =
                    rtrim($path, DIRECTORY_SEPARATOR) .
                    DIRECTORY_SEPARATOR .
                    str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $component) .
                    '.' .
                    ltrim((string) $extension, '.');

                if (is_file($file)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getArrayParameter(string $name): array
    {
        if ($this->container->hasParameter($name)) {
            return (array) $this->container->getParameter($name);
        }

        $values = [];

        for (
            $index = 0;
            $this->container->hasParameter($name . '.' . $index);
            $index++
        ) {
            $values[] = $this->container->getParameter($name . '.' . $index);
        }

        return $values;
    }

    private function filterProps(array $props, string $component): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (
            $request->headers->get('X-Inertia-Partial-Component') !== $component
        ) {
            return $this->filterDefaultProps($props);
        }

        $only = $this->parseHeaderList('X-Inertia-Partial-Data');
        $except = $this->parseHeaderList('X-Inertia-Partial-Except');

        if ($only !== []) {
            $filtered = [];

            foreach ($only as $path) {
                if (self::hasPath($props, $path)) {
                    self::setPath(
                        $filtered,
                        $path,
                        self::getPath($props, $path)
                    );
                }
            }
        } else {
            $filtered = $this->filterDefaultProps($props);
        }

        foreach ($except as $path) {
            self::forgetPath($filtered, $path);
        }

        foreach ($props as $key => $prop) {
            if ($prop instanceof Prop && $prop->isAlways()) {
                $filtered[$key] = $prop;
            }
        }

        $this->removeSkippedOnceProps($filtered);

        return $filtered;
    }

    private function resolveProps(array $props, array &$rescuedProps): array
    {
        foreach ($props as $key => $prop) {
            $resolved = $this->resolveProp($prop, (string) $key, $rescuedProps);

            if ($resolved === $this->missingProp) {
                unset($props[$key]);
                continue;
            }

            $props[$key] = $resolved;
        }

        return $props;
    }

    private function resolveProp(
        mixed $prop,
        string $path,
        array &$rescuedProps
    ): mixed {
        try {
            if ($prop instanceof Prop) {
                $prop = $prop->resolve();
            } elseif ($prop instanceof Closure) {
                $prop = $prop();
            }
        } catch (\Throwable $exception) {
            if (
                $prop instanceof Prop &&
                $prop->isDeferred() &&
                $prop->shouldRescue()
            ) {
                $rescuedProps[] = $path;

                return $this->missingProp;
            }

            throw $exception;
        }

        if (is_array($prop)) {
            foreach ($prop as $key => $value) {
                $resolved = $this->resolveProp(
                    $value,
                    $path . '.' . (string) $key,
                    $rescuedProps
                );

                if ($resolved === $this->missingProp) {
                    unset($prop[$key]);
                    continue;
                }

                $prop[$key] = $resolved;
            }
        }

        return $prop;
    }

    private function resolveDeferredProps(array $props): array
    {
        $deferred = [];

        $this->walkProps($props, function (string $path, mixed $prop) use (
            &$deferred
        ): void {
            if ($prop instanceof Prop && $prop->isDeferred()) {
                $deferred[$prop->getGroup()][] = $path;
            }
        });

        return $deferred;
    }

    private function filterDefaultProps(
        array $props,
        string $prefix = ''
    ): array {
        foreach ($props as $key => $prop) {
            $path =
                $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (
                $prop instanceof Prop &&
                ($prop->isOptional() || $prop->isDeferred())
            ) {
                unset($props[$key]);
                continue;
            }

            if (
                $prop instanceof Prop &&
                $prop->isOnce() &&
                $this->shouldSkipOnceProp($path, $prop)
            ) {
                unset($props[$key]);
                continue;
            }

            if (is_array($prop)) {
                $props[$key] = $this->filterDefaultProps($prop, $path);
            }
        }

        return $props;
    }

    private function resolveOnceProps(array $props): array
    {
        $once = [];

        $this->walkProps($props, function (string $path, mixed $prop) use (
            &$once
        ): void {
            if ($prop instanceof Prop && $prop->isOnce()) {
                $key = $prop->getOnceKey($path);
                $once[$key] = [
                    'prop' => $path,
                    'expiresAt' => $prop->getOnceExpiresAt(),
                ];
            }
        });

        return $once;
    }

    private function resolveMergeMetadata(array $props): array
    {
        $metadata = [
            'mergeProps' => [],
            'prependProps' => [],
            'deepMergeProps' => [],
            'matchPropsOn' => [],
            'scrollProps' => new \stdClass(),
        ];
        $reset = $this->parseHeaderList('X-Inertia-Reset');

        $this->walkProps($props, function (string $path, mixed $prop) use (
            &$metadata,
            $reset
        ): void {
            if (
                !($prop instanceof Prop) ||
                !$prop->isMerge() ||
                in_array($path, $reset, true)
            ) {
                return;
            }

            if ($prop->isScroll()) {
                $metadata['scrollProps']->{$path} = $prop->getScrollMetadata();
                $metadata['mergeProps'] = array_merge(
                    $metadata['mergeProps'],
                    $prop->getAppendPaths($path)
                );
            } elseif ($prop->isDeep()) {
                $metadata['deepMergeProps'][] = $path;
            } else {
                $metadata['mergeProps'] = array_merge(
                    $metadata['mergeProps'],
                    $prop->getAppendPaths($path)
                );
            }

            $metadata['prependProps'] = array_merge(
                $metadata['prependProps'],
                $prop->getPrependPaths($path)
            );

            $metadata['matchPropsOn'] = array_merge(
                $metadata['matchPropsOn'],
                $prop->getMatchPropsOn($path)
            );
        });

        return array_filter(
            $metadata,
            fn($value) => $value instanceof \stdClass
                ? get_object_vars($value) !== []
                : $value !== []
        );
    }

    private function resolveSharedProps(): array
    {
        if (
            $this->container->hasParameter('inertia.expose_shared_prop_keys') &&
            !$this->container->getParameter('inertia.expose_shared_prop_keys')
        ) {
            return [];
        }

        return array_values(
            array_unique(
                array_merge(
                    array_keys($this->sharedProps),
                    array_keys($this->sharedOnceProps)
                )
            )
        );
    }

    private function removeSkippedOnceProps(
        array &$props,
        string $prefix = ''
    ): void {
        foreach ($props as $key => &$prop) {
            $path =
                $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (
                $prop instanceof Prop &&
                $prop->isOnce() &&
                $this->shouldSkipOnceProp($path, $prop)
            ) {
                unset($props[$key]);
                continue;
            }

            if (is_array($prop)) {
                $this->removeSkippedOnceProps($prop, $path);
            }
        }
    }

    private function shouldSkipOnceProp(string $path, Prop $prop): bool
    {
        if ($prop->isFresh()) {
            return false;
        }

        $requested = $this->parseHeaderList('X-Inertia-Partial-Data');

        if (
            in_array($path, $requested, true) ||
            in_array($prop->getOnceKey($path), $requested, true)
        ) {
            return false;
        }

        return in_array(
            $prop->getOnceKey($path),
            $this->parseHeaderList('X-Inertia-Except-Once-Props'),
            true
        );
    }

    private function walkProps(
        array $props,
        callable $callback,
        string $prefix = ''
    ): void {
        foreach ($props as $key => $prop) {
            $path =
                $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            $callback($path, $prop);

            if (is_array($prop)) {
                $this->walkProps($prop, $callback, $path);
            }
        }
    }

    private function parseHeaderList(string $header): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $request->headers->get($header) ?? '')
                )
            )
        );
    }

    private static function hasPath(array $array, string $path): bool
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    private static function getPath(array $array, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            $array = $array[$segment];
        }

        return $array;
    }

    private static function setPath(
        array &$array,
        string $path,
        mixed $value
    ): void {
        $target = &$array;

        foreach (explode('.', $path) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    private static function forgetPath(array &$array, string $path): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        $target = &$array;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }
}
