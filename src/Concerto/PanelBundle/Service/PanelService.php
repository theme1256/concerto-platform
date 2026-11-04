<?php

namespace Concerto\PanelBundle\Service;

use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\HttpFoundation\Session\Session;

class PanelService {

    public function getLoginErrors($authError, Session $session) {
        $errors = null;
        if ($authError !== null) {
            $errors = $authError;
        } else {
            $errors = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
            $session->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        }
        return $errors;
    }
}
