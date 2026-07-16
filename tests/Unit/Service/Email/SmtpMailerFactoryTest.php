<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\Service\Email\Exception\IncompleteSmtpSettingsException;
use App\Service\Email\SmtpMailerFactory;
use App\Service\Settings\SettingsProvider;
use App\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpMailerFactoryTest extends TestCase
{
    public function testFromAddressFailsWhenFromEmailMissing(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([]));

        $this->expectException(IncompleteSmtpSettingsException::class);
        $factory->fromAddress();
    }

    public function testFromAddressFailsOnInvalidFromEmail(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_from_email' => 'not-an-email',
        ]));

        $this->expectException(IncompleteSmtpSettingsException::class);
        $factory->fromAddress();
    }

    public function testFromAddressFallsBackToCompanyName(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_from_email' => 'from@example.com',
            'email.smtp_from_name' => '',
            'company.company_name' => 'Acme Garage',
        ]));

        $address = $factory->fromAddress();

        $this->assertSame('from@example.com', $address->getAddress());
        $this->assertSame('Acme Garage', $address->getName());
    }

    public function testCreateFailsWhenHostMissing(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_from_email' => 'from@example.com',
            'email.smtp_port' => 587,
        ]));

        $this->expectException(IncompleteSmtpSettingsException::class);
        $factory->create();
    }

    public function testCreateFailsWhenPortInvalid(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_host' => 'smtp.example.com',
            'email.smtp_port' => 0,
        ]));

        $this->expectException(IncompleteSmtpSettingsException::class);
        $factory->create();
    }

    public function testCreateBuildsEsmtpTransport(): void
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_host' => 'smtp.example.com',
            'email.smtp_port' => 587,
            'email.smtp_encryption' => 'tls',
            'email.smtp_username' => 'apikey',
            'email.smtp_password' => 'enc1:xxxxx',
        ]));

        $mailer = $factory->create();

        $this->assertInstanceOf(\Symfony\Component\Mailer\Mailer::class, $mailer);
    }

    /**
     * "TLS" in Settings means STARTTLS (typically port 587): the socket must
     * NOT start in implicit-TLS mode, and the transport must refuse to
     * proceed if the server never offers STARTTLS.
     */
    public function testTlsChoiceUsesStartTlsNotImplicitTls(): void
    {
        $transport = $this->buildTransport('tls', 587);

        $stream = $transport->getStream();
        $this->assertInstanceOf(\Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream::class, $stream);
        $this->assertFalse($stream->isTLS(), 'STARTTLS connections must start in plaintext.');
        $this->assertTrue($transport->isTlsRequired(), 'TLS choice must fail closed when STARTTLS is unavailable.');
    }

    public function testSslChoiceUsesImplicitTls(): void
    {
        $transport = $this->buildTransport('ssl', 465);

        $stream = $transport->getStream();
        $this->assertInstanceOf(\Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream::class, $stream);
        $this->assertTrue($stream->isTLS(), 'SSL choice must open an implicit-TLS (smtps) connection.');
    }

    public function testNoneChoiceUsesPlainConnection(): void
    {
        $transport = $this->buildTransport('none', 1025);

        $stream = $transport->getStream();
        $this->assertInstanceOf(\Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream::class, $stream);
        $this->assertFalse($stream->isTLS());
        $this->assertFalse($transport->isTlsRequired());
    }

    private function buildTransport(string $encryption, int $port): EsmtpTransport
    {
        $factory = new SmtpMailerFactory($this->stubProvider([
            'email.smtp_host' => 'smtp.example.com',
            'email.smtp_port' => $port,
            'email.smtp_encryption' => $encryption,
        ]));

        $mailer = $factory->create();

        // Mailer keeps its transport private; reflection is acceptable in a
        // unit test that exists precisely to pin transport configuration.
        $property = new \ReflectionProperty(\Symfony\Component\Mailer\Mailer::class, 'transport');
        $transport = $property->getValue($mailer);
        $this->assertInstanceOf(EsmtpTransport::class, $transport);

        return $transport;
    }

    /**
     * @param array<string, mixed> $values map of "section.key" => value
     */
    private function stubProvider(array $values): SettingsProvider
    {
        return new class($values) extends SettingsProvider {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values)
            {
                // Intentionally skip parent constructor - we only need the
                // documented read interface for these tests.
            }

            public function get(string $section, string $key): mixed
            {
                return $this->values[$section . '.' . $key] ?? null;
            }

            public function getString(string $section, string $key): ?string
            {
                $v = $this->values[$section . '.' . $key] ?? null;

                return $v === null ? null : (string) $v;
            }

            public function getInt(string $section, string $key): ?int
            {
                $v = $this->values[$section . '.' . $key] ?? null;
                if ($v === null || $v === '') {
                    return null;
                }

                return (int) $v;
            }

            public function getFloat(string $section, string $key): ?float
            {
                return null;
            }

            public function getBool(string $section, string $key): bool
            {
                return false;
            }

            public function decryptSecret(string $section, string $key): ?string
            {
                $v = $this->values[$section . '.' . $key] ?? null;

                return $v === null ? null : 'plaintext';
            }

            public function isSet(string $section, string $key): bool
            {
                return isset($this->values[$section . '.' . $key]);
            }

            public function valuesForSection(string $section): array
            {
                return [];
            }

            public function clearCache(): void
            {
            }
        };
    }
}
