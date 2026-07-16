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
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
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

        $output = $pdf->output();
        if ($output === null) {
            throw new \RuntimeException('Dompdf returned no output.');
        }

        return $output;
    }

    private function createDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // Allow reading local files for the logo (restricted to the project public dir).
        $options->set('chroot', [rtrim($this->projectDir, '/') . '/public']);

        return new Dompdf($options);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? 'document';
        $filename = trim($filename, '-');

        return $filename !== '' ? $filename : 'document.pdf';
    }
}
