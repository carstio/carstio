<?php

declare(strict_types=1);

namespace App\Service\Attachment;

use App\Entity\Attachment\Attachment;
use App\Enum\AttachmentOwnerType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Physical-file side of attachments.
 *
 * Files live OUTSIDE the public web root (var/storage/attachments/...) so
 * that no path can be served directly by the web server. Authenticated
 * controller routes are the only way to access uploaded content.
 *
 * Layout: {root}/{ownerType}/{Y}/{m}/{hashPrefix}/{storedFilename}
 *
 * The stored filename is random (hex), so the original client-supplied
 * name never appears on disk and cannot leak metadata even if the storage
 * directory is browsed.
 */
class AttachmentStorage
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly string $storageRoot,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return array{
     *     storedFilename: string,
     *     storagePath: string,
     *     extension: string|null,
     *     mimeType: string,
     *     size: int,
     *     originalFilename: string,
     * }
     */
    public function store(UploadedFile $file, AttachmentOwnerType $ownerType): array
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if ($extension === '') {
            $extension = 'bin';
        }

        $randomName = bin2hex(random_bytes(16));
        $storedFilename = $extension !== '' ? sprintf('%s.%s', $randomName, $extension) : $randomName;

        $now = new \DateTimeImmutable();
        $relativeDir = sprintf(
            '%s/%s/%s/%s',
            $ownerType->value,
            $now->format('Y'),
            $now->format('m'),
            substr($randomName, 0, 2),
        );

        $absoluteDir = $this->storageRoot . '/' . $relativeDir;
        if (!$this->filesystem->exists($absoluteDir)) {
            $this->filesystem->mkdir($absoluteDir, 0775);
        }

        $size = $file->getSize() !== false ? $file->getSize() : 0;
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $originalFilename = $file->getClientOriginalName();

        $file->move($absoluteDir, $storedFilename);

        return [
            'storedFilename' => $storedFilename,
            'storagePath' => $relativeDir . '/' . $storedFilename,
            'extension' => $extension !== '' ? $extension : null,
            'mimeType' => $mimeType,
            'size' => (int) $size,
            'originalFilename' => $originalFilename,
        ];
    }

    public function absolutePath(Attachment $attachment): string
    {
        return $this->storageRoot . '/' . $attachment->getStoragePath();
    }

    public function exists(Attachment $attachment): bool
    {
        return $this->filesystem->exists($this->absolutePath($attachment));
    }

    /**
     * Removes the physical file. Missing files are tolerated so a
     * half-broken record can still be cleaned up by callers.
     */
    public function delete(Attachment $attachment): void
    {
        $path = $this->absolutePath($attachment);
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
