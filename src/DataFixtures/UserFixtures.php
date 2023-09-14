<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $encoder;

    public function __construct(UserPasswordHasherInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager): void
    {
        // Users and roles
        $users = [
            ['email' => 'sergheipowork@gmail.com', 'password' => 'password', 'role' => 'ROLE_ADMIN'],
            ['email' => 'client@gmail.com', 'password' => 'password', 'role' => 'ROLE_CLIENT'],
            ['email' => 'account@gmail.com', 'password' => 'password', 'role' => 'ROLE_ACCOUNT'],
            ['email' => 'alexgalben@gmail.com', 'password' => 'password', 'role' => 'ROLE_CLIENT'],
            ['email' => 'alex22@gmail.com', 'password' => 'password', 'role' => 'ROLE_CLIENT'],
            ['email' => 'alex2233@gmail.com', 'password' => 'password', 'role' => 'ROLE_CLIENT'],
        ];

//         Set and save all $users;
        foreach ($users as $user) {
            $newUser = new User();
            $newUser->setEmail($user['email']);
            $newUser->setPassword(
                $this->encoder->hashPassword($newUser, $user['password'])
            );
            $newUser->setRoles([$user['role']]);
            $manager->persist($newUser);
            $manager->flush();
        }
    }
}
