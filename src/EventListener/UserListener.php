<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\Event\LifecycleEventArgs;

class UserListener
{
    public function prePersist(User $user, LifecycleEventArgs $event): void
    {
        if (in_array('ROLE_CLIENT', $user->getRoles())) {
            $user->setCredit(1000);
        }
    }
}
