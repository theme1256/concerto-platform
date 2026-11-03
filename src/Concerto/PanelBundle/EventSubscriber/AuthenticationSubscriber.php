<?php

namespace Concerto\PanelBundle\EventSubscriber;

use Concerto\PanelBundle\Repository\UserRepository;
use Concerto\PanelBundle\Service\AdministrationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Concerto\PanelBundle\Entity\User;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    private $userRepository;
    private $adminService;

    public function __construct(UserRepository $userRepository, AdministrationService $adminService)
    {
        $this->userRepository = $userRepository;
        $this->adminService = $adminService;
    }

    public static function getSubscribedEvents(): array
    {
        return array(
            LoginFailureEvent::class => 'onAuthenticationFailure',
            LoginSuccessEvent::class => 'onAuthenticationSuccess'
        );
    }

    private static function getAttemptedUsername(LoginFailureEvent $event)
    {
        $passport = $event->getPassport();
        if ($passport !== null && $passport->hasBadge(UserBadge::class)) {
            return $passport->getBadge(UserBadge::class)->getUserIdentifier();
        }
        return $event->getRequest()->request->get('_username');
    }

    public function onAuthenticationFailure(LoginFailureEvent $event)
    {
        if ($this->adminService->isFailedAuthLockEnabled()) {
            $username = self::getAttemptedUsername($event);
            if ($username === null || $username === '') {
                return;
            }
            $maxStreak = (int)$this->adminService->getFailedAuthLockStreak();
            $duration = (int)$this->adminService->getFailedAuthLockTime();

            /** @var User $user */
            $user = $this->userRepository->findOneBy(array("username" => $username));
            if ($user && $user->isAccountNonLocked()) {
                $failedStreak = $user->getFailedAuthenticationStreak() + 1;
                $user->setFailedAuthenticationStreak($failedStreak);
                if ($failedStreak >= $maxStreak) {
                    $until = new \DateTime();
                    $until->setTimestamp(time() + $duration);
                    $user->setLockedUntil($until);
                }
                $this->userRepository->save($user);
            }
        }
    }

    public function onAuthenticationSuccess(LoginSuccessEvent $event)
    {
        /** @var User $user */
        $user = $event->getUser();
        if (is_a($user, User::class) && $user->getFailedAuthenticationStreak() > 0) {
            $user->setFailedAuthenticationStreak(0);
            $this->userRepository->save($user);
        }
    }

}