<?php

namespace App\Service\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Thin wrapper around Dompdf for rendering Twig templates to PDF.
 *
 * Keeps Dompdf configuration in one place and exposes a single
 * downloadResponse() entry point so controllers stay thin.
 */
class PdfRenderer
{
    /**
     * @param string|null $uploadsDir where uploaded images (the company logo)
     *                                are stored, normally public/uploads
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
        private readonly ?string $uploadsDir = null,
    ) {
    }

    /**
     * Renders a Twig template and returns a PDF Response.
     *
     * @param array<string, mixed> $context
     */
    public function downloadResponse(
        string $template,
        array $context,
        string $filename,
        string $disposition = 'attachment',
    ): Response {
        $output = $this->renderToString($template, $context);
        $safeFilename = $this->sanitizeFilename($filename);

        $response = new Response($output);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            sprintf('%s; filename="%s"', $disposition, $safeFilename),
        );

        return $response;
    }

    /**
     * Renders a Twig template and returns the raw PDF bytes. Useful when
     * the binary needs to be embedded somewhere other than an HTTP response
     * (e.g. as an email attachment).
     *
     * @param array<string, mixed> $context
     */
    public function renderToString(string $template, array $context): string
    {
        $html = $this->twig->render($template, $context);
        $pdf = $this->createDompdf();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    private function createDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $this->readableDirectories());

        return new Dompdf($options);
    }

    /**
     * The directories Dompdf may read local files (the logo) from: the
     * public directory and the uploads directory.
     *
     * Dompdf compares real paths. On servers that deploy into release
     * directories public/uploads is usually a symlink to a shared location
     * outside of public/, so that location has to be allowed by its real
     * path or every uploaded logo is refused and missing from the PDF.
     *
     * @return list<string>
     */
    private function readableDirectories(): array
    {
        $directories = [rtrim($this->projectDir, '/') . '/public'];

        $uploads = $this->uploadsDir !== null ? realpath($this->uploadsDir) : false;
        if ($uploads !== false) {
            $directories[] = $uploads;
        }

        return $directories;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'document';
        $filename = trim($filename, '-');

        return $filename !== '' ? $filename : 'document.pdf';
    }
}
