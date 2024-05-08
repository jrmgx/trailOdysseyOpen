<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @param array<int, string> $localeAvailable
     */
    public function __construct(
        private readonly array $localeAvailable,
        private readonly string $localeRequirement,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Localisation only applies to those routes
        if (
            !\in_array($request->attributes->get('_route'), ['root', 'home', 'help'], true)
            && !preg_match('`^/(trip|gear|public|register|login|logout)`', $path)
        ) {
            return;
        }

        if (!preg_match('`^/(' . $this->localeRequirement . ')(/|$)`', $path)) {
            $locale = $request->getLocale();
            if (\in_array($locale, $this->localeAvailable, true)) {
                $event->setResponse(new RedirectResponse("/$locale$path"));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
