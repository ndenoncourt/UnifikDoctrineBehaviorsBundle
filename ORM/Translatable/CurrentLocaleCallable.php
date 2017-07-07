<?php

namespace Unifik\DoctrineBehaviorsBundle\ORM\Translatable;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CurrentLocaleCallable
 */
class CurrentLocaleCallable
{
    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var RequestStack $requestStack
     */
    private $requestStack;

    /**
     * Constructor
     *
     * @param Container $container
     */
    public function __construct(Container $container, RequestStack $requestStack)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
    }

    /**
     * Called when used in a closure
     *
     * @return mixed|string
     */
    public function __invoke()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // In the Backend application, we want the editLocale
        if ($this->container->get('unifik_system.core')->isLoaded() && $this->container->get('unifik_system.core')->getCurrentAppName() == 'backend') {
            return $this->container->get('unifik_backend.core')->getEditLocale();
        }

        // Request Locale
        if ($locale = $request->getLocale()) {
            return $locale;
        }

        // System locale
        return $this->container->getParameter('locale');
    }
}

