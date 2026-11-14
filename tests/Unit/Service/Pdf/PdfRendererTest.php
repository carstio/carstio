<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pdf;

use App\Service\Pdf\PdfRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The logo must make it into the PDF on both common server layouts:
 * uploads as a plain directory, and uploads as a symlink to a shared
 * directory outside public/ (deployments that switch release directories).
 */
final class PdfRendererTest extends TestCase
{
    /** A 10x10 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mNk+M9Qz0AEYBxVSF+FAP0QDgGc0T2oAAAAAElFTkSuQmCC';

    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/carstio-pdf-' . bin2hex(random_bytes(6));
        $GLOBALS['_dompdf_warnings'] = [];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->base);
    }

    public function testALogoInAPlainUploadsDirectoryIsRead(): void
    {
        $projectDir = $this->base . '/app';
        $logo = $this->writeLogo($projectDir . '/public/uploads/settings/company');

        $pdf = $this->renderer($projectDir)->renderToString('document', ['logo' => $logo]);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame([], $this->imageWarnings());
    }

    public function testALogoBehindASymlinkedUploadsDirectoryIsRead(): void
    {
        $projectDir = $this->base . '/releases/1';
        $shared = $this->base . '/shared/uploads';
        $this->writeLogo($shared . '/settings/company');
        (new Filesystem())->mkdir($projectDir . '/public');
        symlink($shared, $projectDir . '/public/uploads');

        $pdf = $this->renderer($projectDir)->renderToString('document', [
            'logo' => $projectDir . '/public/uploads/settings/company/logo.png',
        ]);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame([], $this->imageWarnings(), 'The shared uploads directory must be readable through its real path.');
    }

    public function testFilesOutsideThePublicAndUploadsDirectoriesStayOutOfReach(): void
    {
        $projectDir = $this->base . '/app';
        $this->writeLogo($projectDir . '/public/uploads/settings/company');
        $secret = $this->writeLogo($this->base . '/elsewhere');

        $this->renderer($projectDir)->renderToString('document', ['logo' => $secret]);

        self::assertNotSame([], $this->imageWarnings(), 'Allowing the uploads directory must not open up the rest of the file system.');
    }

    private function renderer(string $projectDir): PdfRenderer
    {
        $twig = new Environment(new ArrayLoader(['document' => '<html><body><img src="{{ logo }}" width="40"><p>Invoice</p></body></html>']));

        return new PdfRenderer($twig, $projectDir, $projectDir . '/public/uploads');
    }

    private function writeLogo(string $directory): string
    {
        (new Filesystem())->dumpFile($directory . '/logo.png', (string) base64_decode(self::PNG, true));

        return $directory . '/logo.png';
    }

    /**
     * @return list<string> what Dompdf reported about images it could not load
     */
    private function imageWarnings(): array
    {
        return array_values(array_filter(
            (array) ($GLOBALS['_dompdf_warnings'] ?? []),
            static fn (mixed $warning): bool => \is_string($warning) && str_contains($warning, 'logo.png'),
        ));
    }
}
