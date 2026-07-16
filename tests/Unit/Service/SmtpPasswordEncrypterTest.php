<?php

namespace App\Tests\Unit\Service;

use App\Service\Settings\SmtpPasswordEncrypter;
use PHPUnit\Framework\TestCase;

class SmtpPasswordEncrypterTest extends TestCase
{
    public function testRoundTripDecryptsToOriginalValue(): void
    {
        $encrypter = new SmtpPasswordEncrypter('test-secret');
        $cipher = $encrypter->encrypt('p@ssw0rd!');

        $this->assertStringStartsWith('enc1:', $cipher);
        $this->assertSame('p@ssw0rd!', $encrypter->decrypt($cipher));
    }

    public function testCiphertextIsNotPlaintext(): void
    {
        $encrypter = new SmtpPasswordEncrypter('test-secret');
        $cipher = $encrypter->encrypt('super-secret');

        $this->assertStringNotContainsString('super-secret', $cipher);
    }

    public function testCiphertextIsNonDeterministic(): void
    {
        // Different nonces per call, so the same input must produce
        // different ciphertexts each time.
        $encrypter = new SmtpPasswordEncrypter('test-secret');

        $this->assertNotSame(
            $encrypter->encrypt('value'),
            $encrypter->encrypt('value'),
        );
    }

    public function testDecryptReturnsNullForNullOrEmpty(): void
    {
        $encrypter = new SmtpPasswordEncrypter('test-secret');

        $this->assertNull($encrypter->decrypt(null));
        $this->assertNull($encrypter->decrypt(''));
    }

    public function testDecryptReturnsNullForUnprefixedValue(): void
    {
        // Plain or legacy values must not crash decryption: callers can
        // gracefully fall back to "no usable password".
        $encrypter = new SmtpPasswordEncrypter('test-secret');

        $this->assertNull($encrypter->decrypt('plaintext-leak'));
    }

    public function testDecryptReturnsNullWhenSecretChanged(): void
    {
        $original = new SmtpPasswordEncrypter('first-secret');
        $cipher = $original->encrypt('value');

        $rotated = new SmtpPasswordEncrypter('second-secret');
        $this->assertNull($rotated->decrypt($cipher));
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        new SmtpPasswordEncrypter('');
    }
}
