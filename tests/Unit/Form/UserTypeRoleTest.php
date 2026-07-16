<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class UserTypeRoleTest extends KernelTestCase
{
    public function testInvalidRoleMakesFormInvalid(): void
    {
        $factory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $factory->create(UserType::class, new \App\Entity\User());
        $form->submit([
            'email' => 'bad@test.local',
            'fullName' => 'Bad',
            'plainPassword' => 'longenough123',
            'role' => 'ROLE_SUPERUSER',
            'isActive' => '1',
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse(
            $form->isValid(),
            'Submitting an unknown role string must produce an invalid form. '
            . 'Errors: ' . implode('; ', array_map(
                fn ($e) => $e->getOrigin()?->getName() . ' -- ' . $e->getMessage(),
                iterator_to_array($form->getErrors(true)),
            )),
        );
    }
}
