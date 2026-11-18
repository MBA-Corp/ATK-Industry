<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class CatalogWorkbookReader
{
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Invalid XLSX archive.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 500) {
                $this->fail('Too many archive entries.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                if ($size > 30000000 || str_contains($entry['name'], '../')) {
                    $this->fail('Workbook exceeds safe extraction limits.');
                }
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $sheetId = null;
            foreach ($workbook->sheets->sheet as $sheet) {
                if ((string) $sheet['name'] === 'Satuan') {
                    $sheetId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                }
            }
            $target = null;
            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $sheetId && (string) $relationship['TargetMode'] !== 'External') {
                    $target = ltrim((string) $relationship['Target'], '/');
                    $target = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
            if ($target === null || ! preg_match('#^xl/worksheets/[a-zA-Z0-9_.-]+\.xml$#', $target)) {
                $this->fail('Sheet Satuan is required.');
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->si as $item) {
                    $strings[] = $this->text($item);
                    if (count($strings) > 50000) {
                        $this->fail('Too many shared strings.');
                    }
                }
            }
            $headers = null;
            $rows = [];
            foreach ($this->xml($zip, $target)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    if (isset($cell->f)) {
                        $this->fail('Formulas are not accepted; upload a values-only workbook.');
                    }
                    $column = preg_replace('/[0-9]/', '', (string) $cell['r']);
                    if (! preg_match('/^[A-Z]{1,2}$/', $column) || count($cells) >= 80) {
                        $this->fail('Invalid column reference.');
                    }
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->text($cell->is),
                        default => (string) $cell->v,
                    };
                    $cells[$column] = trim($value);
                }
                if ($headers === null) {
                    $headers = $cells;
                    if (! in_array('KODEITEM', $headers, true) || count($headers) !== count(array_unique($headers))) {
                        $this->fail('Invalid or duplicate column headers.');
                    }

                    continue;
                }
                if (count(array_filter($cells, fn ($v) => $v !== '')) === 0) {
                    continue;
                }
                $record = [];
                foreach ($headers as $column => $header) {
                    $record[$header] = $cells[$column] ?? '';
                }
                $rows[] = $record;
                if (count($rows) > 3000) {
                    $this->fail('Maximum 3000 data rows per import.');
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $xml = $zip->getFromName($path);
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            $this->fail('Missing or unsafe workbook XML.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            if ($parsed === false) {
                $this->fail('Invalid workbook XML.');
            }

            return $parsed;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $element): string
    {
        $result = (string) $element->t;
        foreach ($element->r as $run) {
            $result .= (string) $run->t;
        }

        return $result;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
