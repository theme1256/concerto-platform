<?php

namespace Concerto\APIBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Backwards compatibility with FOSOAuthServerBundle, which also accepted the access token
 * as an "access_token" query or form parameter. league/oauth2-server only reads the
 * Authorization header, so the parameter is copied there before the firewall runs.
 */
class AccessTokenParameterSubscriber implements EventSubscriberInterface
{
    const PARAMETER = 'access_token';
    /** Must run before the security firewall (priority 8). */
    const PRIORITY = 10;

    public static function getSubscribedEvents(): array
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', self::PRIORITY),
        );
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (strpos($request->getPathInfo(), '/api/') !== 0 || $request->headers->has('Authorization')) {
            return;
        }

        $token = $request->query->get(self::PARAMETER, $request->request->get(self::PARAMETER));
        if (is_string($token) && $token !== '') {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }
    }
}
