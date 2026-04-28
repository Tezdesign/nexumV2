<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class AdminPdfExportService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderTablePdf(string $twigTemplate, array $context, string $downloadFilename, string $orientation = 'landscape'): Response
    {
        $html = $this->twig->render($twigTemplate, $context);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation === 'portrait' ? 'portrait' : 'landscape');
        $dompdf->render();

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $downloadFilename);
        $safeName = (string) $safeName;
        if ($safeName === '' || $safeName === '-') {
            $safeName = 'export.pdf';
        }
        if (!str_ends_with(strtolower((string) $safeName), '.pdf')) {
            $safeName .= '.pdf';
        }

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$safeName.'"',
            ]
        );
    }
}
