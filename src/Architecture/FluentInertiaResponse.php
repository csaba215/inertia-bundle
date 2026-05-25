<?php

namespace Rompetomp\InertiaBundle\Architecture;

use Rompetomp\InertiaBundle\Service\InertiaService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FluentInertiaResponse extends Response
{
    private ?Response $response = null;

    public function __construct(
        private InertiaService $inertia,
        private string $component,
        private array $props = [],
        private array $viewData = [],
        private array $context = [],
        private ?string $url = null,
        private ?string $rootView = null
    ) {
        parent::__construct();
    }

    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this->invalidate();
    }

    public function withViewData(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this->invalidate();
    }

    public function rootView(string $rootView): static
    {
        $this->rootView = $rootView;

        return $this->invalidate();
    }

    public function flash(string|array $key, mixed $value = null): static
    {
        $this->inertia->flash($key, $value);

        return $this->invalidate();
    }

    public function toResponse(): Response
    {
        if ($this->response === null) {
            $this->response = $this->inertia->renderResponse(
                $this->component,
                $this->props,
                $this->viewData,
                $this->context,
                $this->url,
                $this->rootView
            );
            $this->syncResponse($this->response);
        }

        return $this->response;
    }

    public function prepare(Request $request): static
    {
        $response = $this->toResponse();
        $response->prepare($request);
        $this->syncResponse($response);

        return $this;
    }

    public function getContent(): string|false
    {
        return $this->toResponse()->getContent();
    }

    public function sendHeaders(?int $statusCode = null): static
    {
        $this->toResponse();

        return parent::sendHeaders($statusCode);
    }

    public function sendContent(): static
    {
        $this->toResponse();

        return parent::sendContent();
    }

    private function invalidate(): static
    {
        $this->response = null;

        return $this;
    }

    private function syncResponse(Response $response): void
    {
        $this->headers = clone $response->headers;
        $this->setContent($response->getContent());
        $this->setStatusCode($response->getStatusCode());
        $this->setProtocolVersion($response->getProtocolVersion());

        if ($response->getCharset() !== null) {
            $this->setCharset($response->getCharset());
        }
    }
}
