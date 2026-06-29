<?php

namespace Respinar\FooladgharbRequestBundle\Controller\Backend;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\Database;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class RequestExportController
{
    private const COLUMNS = [
        'ID' => 'id',
        'Name' => 'name',
        'Phone' => 'phone',
        'Email' => 'email',
        'Province' => 'province',
        'Product' => 'product',
        'Type' => 'type',
        'Usage' => 'usage',
        'Insulation' => 'insulation',
        'Area' => 'area',
        'Message' => 'message',
        'Date' => 'date',
        'Time' => 'time',
        'URL' => 'url',
        'Lead ID' => 'lead_id',
        'Client ID' => 'client_id',
        'Created' => 'tstamp',
    ];

    public function exportCsv(): never
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_keys(self::COLUMNS));

        foreach ($this->getRows() as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        throw new ResponseException($this->createDownloadResponse(
            stream_get_contents($handle),
            'requests.csv',
            'text/csv; charset=UTF-8'
        ));
    }

    public function exportExcel(): never
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="Requests"><Table>' . "\n";
        $xml .= $this->buildExcelRow(array_keys(self::COLUMNS));

        foreach ($this->getRows() as $row) {
            $xml .= $this->buildExcelRow($row);
        }

        $xml .= '</Table></Worksheet></Workbook>';

        throw new ResponseException($this->createDownloadResponse(
            $xml,
            'requests.xls',
            'application/vnd.ms-excel; charset=UTF-8'
        ));
    }

    /**
     * @return list<list<string|int|null>>
     */
    private function getRows(): array
    {
        $result = Database::getInstance()
            ->execute('SELECT * FROM tl_requests ORDER BY tstamp DESC');

        $rows = [];

        while ($result->next()) {
            $row = [];

            foreach (self::COLUMNS as $field) {
                $value = $result->{$field};

                if ('tstamp' === $field) {
                    $value = $value ? date('Y-m-d H:i', (int) $value) : '';
                }

                $row[] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string|int|null> $cells
     */
    private function buildExcelRow(array $cells): string
    {
        $row = '<Row>';

        foreach ($cells as $cell) {
            $row .= '<Cell><Data ss:Type="String">'
                . htmlspecialchars((string) $cell, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                . '</Data></Cell>';
        }

        return $row . '</Row>' . "\n";
    }

    private function createDownloadResponse(string $content, string $filename, string $contentType): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );
        $response->headers->set('Content-Length', (string) strlen($content));
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');

        return $response;
    }
}
