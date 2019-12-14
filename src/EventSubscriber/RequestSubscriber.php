<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RequestSubscriber implements EventSubscriberInterface
{
    /** @var RouterInterface */
    protected $router;

    /** @var TokenStorageInterface */
    protected $token_storage;

    /**
     * RequestSubscriber constructor.
     *
     * @param RouterInterface       $router
     * @param TokenStorageInterface $token_storage
     */
    public function __construct(RouterInterface $router, TokenStorageInterface $token_storage)
    {
        $this->router        = $router;
        $this->token_storage = $token_storage;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => 'onKernelRequest',
//            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $token = $this->token_storage->getToken()) {
            return;
        }

        /** @var User $user */
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        if (empty($user->getPatronymic())
            or empty($user->getBirthYear())
            or empty($user->getPassportCode())
            or empty($user->getLatitude())
            or empty($user->getLongitude())
            or $user->getStatus() == User::STATUS_DECLINE
        ) {
            $route = 'profile';

            if ($route === $event->getRequest()->get('_route')
                or strpos($event->getRequest()->get('_route'), 'api_') === 0
            ) {
                return;
            }

            if ($user->getStatus() == User::STATUS_DECLINE) {
                $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Ваша заявка была отклонена. Вы должны поправить ошибку в профиле и опять отправить на заверение.');
            } else {
                $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Первым делом нужно заполнить профиль.');
            }

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        } elseif (!$user->isAllowMessagesFromCommunity()) {
            $route = 'profile_allow_messages_from_community';

            if ($route === $event->getRequest()->get('_route')) {
                return;
            }

            //$event->getRequest()->getSession()->getFlashBag()->add('warning', 'Необходимо разрешить получение уведомлений от сообщества в VK');

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        } elseif ($user->getStatus() == User::STATUS_NEW or $user->getStatus() == User::STATUS_PENDING) {
            $route = 'assurance';

            if ($route === $event->getRequest()->get('_route')) {
                return;
            }

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        }
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $origin = $event->getRequest()->headers->get('origin', '*');

//        if (
//            $event->getRequest()->getPathInfo() === '/api/users/'
//            || $event->getRequest()->getPathInfo() === '/api/user/*' // @todo
//        ) {
            $event->getResponse()->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
            $event->getResponse()->headers->set('Access-Control-Allow-Methods', 'POST');
            $event->getResponse()->headers->set('Access-Control-Allow-Origin', $origin);
//        }
    }
}
