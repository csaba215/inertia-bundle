<?php

namespace Rompetomp\InertiaBundle\Twig;

use Closure;
use Rompetomp\InertiaBundle\Architecture\GatewayInterface;
use Rompetomp\InertiaBundle\Architecture\InertiaInterface;
use Rompetomp\InertiaBundle\Ssr\InertiaSsrResponse;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Registers the required functions for the Inertia Twig Extension.
 *
 * @author  Hannes Vermeire <hannes@codedor.be>
 *
 * @since   2019-08-09
 */
class InertiaTwigExtension extends AbstractExtension
{
    public function __construct(
        private InertiaInterface $inertia,
        private GatewayInterface $gateway
    ) {
    }

    /**
     * Register the functions.
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'inertia',
                Closure::fromCallable([$this, 'inertiaFunction'])
            ),
            new TwigFunction(
                'inertiaHead',
                Closure::fromCallable([$this, 'inertiaHeadFunction'])
            ),
        ];
    }

    /**
     * The inertia function that renders the Inertia root element and page data.
     *
     * @param $page
     * @return Markup
     */
    public function inertiaFunction($page): Markup
    {
        if ($this->inertia->isSsr()) {
            $response = $this->gateway->dispatch($page);
            if ($response instanceof InertiaSsrResponse) {
                return new Markup($response->body, 'UTF-8');
            }
        }

        $json = json_encode(
            $page,
            JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT |
                JSON_THROW_ON_ERROR
        );

        return new Markup(
            '<script data-page="app" type="application/json">' .
                $json .
                '</script><div id="app"></div>',
            'UTF-8'
        );
    }

    /**
     * @param $page
     * @return Markup
     */
    public function inertiaHeadFunction($page): Markup
    {
        if ($this->inertia->isSsr()) {
            $response = $this->gateway->dispatch($page);
            if ($response instanceof InertiaSsrResponse) {
                return new Markup($response->head, 'UTF-8');
            }
        }

        return new Markup('', 'UTF-8');
    }
}
