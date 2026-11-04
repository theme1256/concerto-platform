<?php

namespace Concerto\PanelBundle\Utils;

use Symfony\Component\HttpFoundation\Request;

/**
 * Parameter lookup of the former Request::get() (deprecated in Symfony 7.4, removed in 8.0).
 *
 * Panel and API endpoints accept their parameters from the route, the query string or the
 * request body, and clients (panel JS, test content, API consumers) rely on that, so the lookup
 * order is kept: route attributes, then query, then body. Array values are returned as they are.
 */
final class RequestParameters
{
    public static function get(Request $request, string $key, mixed $default = null): mixed
    {
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        if ($request->query->has($key)) {
            return $request->query->all()[$key];
        }
        if ($request->request->has($key)) {
            return $request->request->all()[$key];
        }
        return $default;
    }
}
