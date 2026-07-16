<?php

namespace App\Service\Settings;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Handles local file storage for the Company logo (and similar image uploads).
 *
 * - stores files under a configured uploads directory
 * - generates a safe, unique filename (never trusts the client-supplied name)
 * - returns the public path to be stored on the settings entity
 * - can remove previously stored files safely
 */
class LogoUploader
{
    private Filesystem $filesystem;
    private AsciiSlugger $slugger;

    public function __construct(
        private readonly string $uploadsDir,
        private readonly string $publicPrefix,
    ) {
        $this->filesystem = new Filesystem();
        $this->slugger = new AsciiSlugger();
    }

    /**
     * Stores the uploaded file and returns its public path (relative URL).
     */
    public function upload(UploadedFile $file): string
    {
        if (!$this->filesystem->exists($this->uploadsDir)) {
            $this->filesystem->mkdir($this->uploadsDir, 0775);
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($original)->lower()->toString();
        if ($safeName === '') {
            $safeName = 'logo';
        }

        $extension = $file->guessExtension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', $safeName, bin2hex(random_bytes(6)), $extension);

        $file->move($this->uploadsDir, $filename);

        return rtrim($this->publicPrefix, '/') . '/' . $filename;
    }

    /**
     * Removes a previously uploaded file by its stored public path.
     * No-op if path is null/empty or the file is already gone.
     */
    public function remove(?string $publicPath): void
    {
        if ($publicPath === null || $publicPath === '') {
            return;
        }

        $filename = basename($publicPath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }

        $absolute = $this->uploadsDir . '/' . $filename;
        if ($this->filesystem->exists($absolute)) {
            $this->filesystem->remove($absolute);
        }
    }
}
