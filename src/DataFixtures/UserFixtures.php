<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_REFERENCE = 'user-admin';
    public const SECONDARY_ADMIN_REFERENCE = 'user-admin-2';
    public const MECHANIC_REFERENCE = 'user-mechanic';
    public const INACTIVE_REFERENCE = 'user-inactive';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@carstio.local');
        $admin->setFullName('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        // Admin has no preferredLocale on purpose: exercises the
        // "fall back to Settings.default_locale" branch.
        $manager->persist($admin);

        // Secondary admin so tests for the "last active admin" guard have a
        // realistic baseline (and so a real workshop demo isn't a single
        // point of failure for admin access).
        $secondaryAdmin = new User();
        $secondaryAdmin->setEmail('owner@carstio.local');
        $secondaryAdmin->setFullName('Workshop Owner');
        $secondaryAdmin->setRoles(['ROLE_ADMIN']);
        $secondaryAdmin->setPassword($this->passwordHasher->hashPassword($secondaryAdmin, 'owner123'));
        $manager->persist($secondaryAdmin);

        $mechanic = new User();
        $mechanic->setEmail('mechanic@carstio.local');
        $mechanic->setFullName('Mechanic');
        $mechanic->setRoles(['ROLE_MECHANIC']);
        $mechanic->setPassword($this->passwordHasher->hashPassword($mechanic, 'mechanic'));
        $mechanic->setPreferredLocale('bg');
        $manager->persist($mechanic);

        // Deactivated mechanic kept around so the "deactivated user cannot
        // log in" path has a fixture-backed user without affecting the
        // happy-path admin/mechanic logins used elsewhere.
        $inactive = new User();
        $inactive->setEmail('former@carstio.local');
        $inactive->setFullName('Former Mechanic');
        $inactive->setRoles(['ROLE_MECHANIC']);
        $inactive->setPassword($this->passwordHasher->hashPassword($inactive, 'former123'));
        $inactive->setIsActive(false);
        $manager->persist($inactive);

        $manager->flush();

        $this->addReference(self::ADMIN_REFERENCE, $admin);
        $this->addReference(self::SECONDARY_ADMIN_REFERENCE, $secondaryAdmin);
        $this->addReference(self::MECHANIC_REFERENCE, $mechanic);
        $this->addReference(self::INACTIVE_REFERENCE, $inactive);
    }
}
