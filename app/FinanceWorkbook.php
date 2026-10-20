<?php

namespace App;

use App\Models\Trip;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use stdClass;
use XMLWriter;
use ZipStream\ZipStream;

class FinanceWorkbook
{
    /** @param Builder<Trip> $query
     * @param  array<string, mixed>  $filters
     */
    public function write(Builder $query, stdClass $totals, array $filters): void
    {
        $archive = new ZipStream(sendHttpHeaders: false, defaultEnableZeroHeader: false);
        $archive->addFile('[Content_Types].xml', $this->contentTypes());
        $archive->addFile('_rels/.rels', $this->packageRelationships());
        $archive->addFile('xl/workbook.xml', $this->workbookXml());
        $archive->addFile('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $archive->addFile('xl/styles.xml', $this->stylesXml());

        $this->addSheet($archive, 'xl/worksheets/sheet1.xml', fn (XMLWriter $xml, $stream) => $this->writeSummary($xml, $stream, clone $query, $totals, $filters));
        $this->addSheet($archive, 'xl/worksheets/sheet2.xml', fn (XMLWriter $xml, $stream) => $this->writeTransactions($xml, $stream, clone $query));

        $archive->finish();
    }

    /** @param callable(XMLWriter, resource): void $write */
    private function addSheet(ZipStream $archive, string $path, callable $write): void
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        try {
            $xml = new XMLWriter;
            $xml->openMemory();
            $write($xml, $stream);
            fwrite($stream, $xml->flush(true));
            rewind($stream);
            $archive->addFileFromStream(fileName: $path, stream: $stream);
        } finally {
            fclose($stream);
        }
    }

    /** @param Builder<Trip> $query
     * @param  array<string, mixed>  $filters
     * @param  resource  $stream
     */
    private function writeSummary(XMLWriter $xml, $stream, Builder $query, stdClass $totals, array $filters): void
    {
        $row = 1;
        $merges = ['A1:L1', 'A2:L2'];
        $metadata = [['Dibuat pada', now()->format('d/m/Y H:i').' WIB']];
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $metadata[] = ['Periode mulai trip', ($filters['from'] ?: 'Awal').' - '.($filters['to'] ?: 'Akhir')];
        }
        if (! empty($filters['status'])) {
            $metadata[] = ['Status trip', TripStatus::from($filters['status'])->label()];
        }
        if (! empty($filters['search'])) {
            $metadata[] = ['Pencarian', $filters['search']];
        }
        $headerRow = 11 + count($metadata);
        $this->startSheet($xml, [19, 33, 26, 26, 25, 23, 20, 22, 20, 22, 22, 22], $headerRow);
        $this->row($xml, $row++, [['ARFAKA TOUR & TRAVEL', 1]], 35);
        $this->row($xml, $row++, [['REKAPITULASI KEUANGAN PERJALANAN', 2]], 30);
        $row++;

        foreach ($metadata as [$label, $value]) {
            $this->row($xml, $row, [[$label, 3], [$value, 4]], 22);
            $merges[] = 'B'.$row.':L'.$row;
            $row++;
        }

        $row++;
        $this->band($xml, $row, 'RINGKASAN HASIL FILTER', 12, 5);
        $merges[] = 'A'.$row.':L'.$row;
        $row++;
        $this->row($xml, $row++, [
            ['Jumlah trip', 3], [(int) $totals->trips, 14, 'number'], null,
            ['Nilai kontrak', 3], [(int) $totals->contracts, 13, 'number'], null,
            ['Pembayaran diterima', 3], [(int) $totals->payments, 13, 'number'],
        ], 29);
        $this->row($xml, $row++, [
            ['Biaya disetujui', 3], [(int) $totals->expenses, 13, 'number'], null,
            ['Piutang customer', 3], [(int) $totals->receivables, 13, 'number'], null,
            ['Margin kontrak sementara', 3], [(int) $totals->profit, 13, 'number'],
        ], 29);
        $this->row($xml, $row, [['Angka berasal dari seluruh umur trip sesuai filter. Hanya transaksi disetujui yang masuk perhitungan.', 22]], 23);
        $merges[] = 'A'.$row.':L'.$row;
        $row += 2;
        $this->band($xml, $row, 'RINCIAN PER TRIP', 12, 5);
        $merges[] = 'A'.$row.':L'.$row;
        $row++;
        $headerRow = $row;
        $this->row($xml, $row++, array_map(fn (string $label): array => [$label, 6], [
            'Kode Trip', 'Nama Trip', 'Customer', 'Destinasi', 'Periode', 'Status',
            'Nilai Kontrak', 'Pembayaran Diterima', 'Total RAB', 'Biaya Disetujui', 'Piutang Customer', 'Margin Sementara',
        ]), 42);

        $budgetTotal = 0;
        $tripCount = 0;
        foreach ((clone $query)->with('customer:id,name')->lazy(100) as $trip) {
            $alternate = $tripCount++ % 2 === 1;
            $body = $alternate ? 8 : 7;
            $money = $alternate ? 10 : 9;
            $budgetTotal += (int) $trip->budget_total;
            $this->row($xml, $row++, [
                [$trip->code, $body], [$trip->name, $body], [$trip->customer->name, $body],
                [$trip->destination, $body], [$trip->start_date->format('d/m/Y').' - '.$trip->end_date->format('d/m/Y'), $body],
                [$trip->status->label(), $this->tripStatusStyle($trip->status)],
                [$trip->contract_amount ?? 'Belum diisi', $trip->contract_amount === null ? $body : $money, $trip->contract_amount === null ? 'text' : 'number'],
                [(int) $trip->payment_total, $money, 'number'], [(int) $trip->budget_total, $money, 'number'],
                [(int) $trip->expense_total, $money, 'number'],
                [$trip->contract_amount === null ? 'Belum diisi' : $trip->contract_amount - (int) $trip->payment_total, $trip->contract_amount === null ? $body : $money, $trip->contract_amount === null ? 'text' : 'number'],
                [$trip->contract_amount === null ? 'Belum diisi' : $trip->contract_amount - (int) $trip->expense_total, $trip->contract_amount === null ? $body : $money, $trip->contract_amount === null ? 'text' : 'number'],
            ], 36);
            fwrite($stream, $xml->flush(true));
        }
        if ($tripCount === 0) {
            $this->row($xml, $row, [['Belum ada trip sesuai filter.', 22]], 27);
            $merges[] = 'A'.$row.':L'.$row;
            $row++;
        }
        $lastDataRow = $row - 1;
        $this->row($xml, $row, [
            ['TOTAL SESUAI FILTER', 15], null, null, null, null, null,
            [(int) $totals->contracts, 16, 'number'], [(int) $totals->payments, 16, 'number'],
            [$budgetTotal, 16, 'number'], [(int) $totals->expenses, 16, 'number'],
            [(int) $totals->receivables, 16, 'number'], [(int) $totals->profit, 16, 'number'],
        ], 31);
        $merges[] = 'A'.$row.':F'.$row;
        $this->endSheet($xml, $merges, 'A'.$headerRow.':L'.$lastDataRow);
    }

    /** @param Builder<Trip> $query
     * @param  resource  $stream
     */
    private function writeTransactions(XMLWriter $xml, $stream, Builder $query): void
    {
        $this->startSheet($xml, [19, 30, 24, 16, 26, 22, 17, 20, 45, 24, 24, 24, 23, 44], 5);
        $this->row($xml, 1, [['ARFAKA TOUR & TRAVEL', 1]], 35);
        $this->row($xml, 2, [['DETAIL TRANSAKSI', 2]], 30);
        $this->row($xml, 3, [['Disetujui memengaruhi rekap. Menunggu, ditolak, dan dibatalkan dicatat untuk pemeriksaan.', 22]], 24);
        $this->row($xml, 5, array_map(fn (string $label): array => [$label, 6], [
            'Kode Trip', 'Nama Trip', 'Customer', 'Tanggal', 'Jenis Transaksi', 'Kategori',
            'Status', 'Nominal', 'Keterangan', 'Tour Leader', 'Dicatat Oleh',
            'Diverifikasi Oleh', 'Waktu Verifikasi', 'Alasan Pemeriksaan',
        ]), 44);

        $row = 6;
        $entryCount = 0;
        foreach ((clone $query)->with([
            'customer:id,name',
            'financeEntries' => fn ($entries) => $entries->with(['creator:id,name', 'leader:id,name', 'reviewer:id,name'])
                ->orderBy('occurred_on')->orderBy('id'),
        ])->lazy(100) as $trip) {
            foreach ($trip->financeEntries as $entry) {
                $alternate = $entryCount++ % 2 === 1;
                $body = $alternate ? 8 : 7;
                $money = $alternate ? 10 : 9;
                $this->row($xml, $row++, [
                    [$trip->code, $body], [$trip->name, $body], [$trip->customer->name, $body],
                    [$this->dateSerial($entry->occurred_on), 11, 'number'], [$entry->type->label(), $body],
                    [$entry->category?->label(), $body], [$this->entryStatusLabel($entry->status), $this->entryStatusStyle($entry->status)],
                    [$entry->amount, $money, 'number'], [$entry->description, $body],
                    [$entry->leader?->name ?? 'Perusahaan', $body], [$entry->creator?->name, $body],
                    [$entry->reviewer?->name, $body],
                    [$entry->reviewed_at ? $this->dateSerial($entry->reviewed_at, true) : null, 23, 'number'],
                    [$entry->review_reason, $body],
                ], 38);
            }
            fwrite($stream, $xml->flush(true));
        }
        $merges = ['A1:N1', 'A2:N2', 'A3:N3'];
        if ($entryCount === 0) {
            $this->row($xml, $row, [['Belum ada transaksi pada trip sesuai filter.', 22]], 27);
            $merges[] = 'A'.$row.':N'.$row;
            $row++;
        }
        $this->endSheet($xml, $merges, 'A5:N'.($row - 1));
    }

    /** @param array<int, int> $widths */
    private function startSheet(XMLWriter $xml, array $widths, int $frozenRow): void
    {
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', (string) $frozenRow);
        $xml->writeAttribute('topLeftCell', 'A'.($frozenRow + 1));
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('sheetFormatPr');
        $xml->writeAttribute('defaultRowHeight', '20');
        $xml->endElement();
        $xml->startElement('cols');
        foreach ($widths as $index => $width) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($index + 1));
            $xml->writeAttribute('max', (string) ($index + 1));
            $xml->writeAttribute('width', (string) $width);
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
    }

    /** @param array<int, string> $merges */
    private function endSheet(XMLWriter $xml, array $merges, string $filterRange): void
    {
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', $filterRange);
        $xml->endElement();
        $xml->startElement('mergeCells');
        $xml->writeAttribute('count', (string) count($merges));
        foreach ($merges as $range) {
            $xml->startElement('mergeCell');
            $xml->writeAttribute('ref', $range);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('pageMargins');
        foreach (['left' => '0.4', 'right' => '0.4', 'top' => '0.6', 'bottom' => '0.6', 'header' => '0.2', 'footer' => '0.2'] as $name => $value) {
            $xml->writeAttribute($name, $value);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
    }

    private function band(XMLWriter $xml, int $row, string $title, int $columns, int $style): void
    {
        $cells = array_fill(0, $columns, [null, $style]);
        $cells[0] = [$title, $style];
        $this->row($xml, $row, $cells, 26);
    }

    /** @param array<int, array{string|int|float|null, int, string?}|null> $cells */
    private function row(XMLWriter $xml, int $number, array $cells, int $height = 24): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        $xml->writeAttribute('ht', (string) $height);
        $xml->writeAttribute('customHeight', '1');
        foreach ($cells as $index => $cell) {
            if ($cell === null) {
                continue;
            }
            [$value, $style] = $cell;
            $reference = chr(65 + $index).$number;
            $xml->startElement('c');
            $xml->writeAttribute('r', $reference);
            $xml->writeAttribute('s', (string) $style);
            if (($cell[2] ?? 'text') === 'number' && $value !== null) {
                $xml->startElement('v');
                $xml->text((string) $value);
                $xml->endElement();
            } elseif ($value !== null) {
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->text($this->cleanText((string) $value));
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function dateSerial(DateTimeInterface $date, bool $withTime = false): int|float
    {
        $value = new DateTimeImmutable($date->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d 00:00:00'), new DateTimeZone('UTC'));
        $serial = $value->getTimestamp() / 86400 + 25569;

        return $withTime ? $serial : (int) $serial;
    }

    private function tripStatusStyle(TripStatus $status): int
    {
        return match ($status) {
            TripStatus::Closed => 17,
            TripStatus::AwaitingSettlement => 18,
            TripStatus::Confirmed, TripStatus::Ongoing => 19,
            TripStatus::Draft => 21,
        };
    }

    private function entryStatusStyle(string $status): int
    {
        return match ($status) {
            'approved' => 17,
            'pending' => 18,
            'rejected' => 20,
            default => 21,
        };
    }

    private function entryStatusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'void' => 'Dibatalkan',
            default => 'Menunggu pemeriksaan',
        };
    }

    private function cleanText(string $value): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    }

    private function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>
XML;
    }

    private function packageRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Ringkasan Keuangan" sheetId="1" r:id="rId1"/><sheet name="Detail Transaksi" sheetId="2" r:id="rId2"/></sheets></workbook>
XML;
    }

    private function workbookRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="3"><numFmt numFmtId="164" formatCode="&quot;Rp&quot; #,##0;[Red](&quot;Rp&quot; #,##0)"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm"/></numFmts>
<fonts count="10"><font><sz val="11"/><color rgb="FF334155"/><name val="Calibri"/></font><font><b/><sz val="18"/><color rgb="FF0F172A"/><name val="Calibri"/></font><font><b/><sz val="13"/><color rgb="FF0F766E"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font><font><b/><sz val="12"/><color rgb="FF0F766E"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF166534"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF92400E"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF1D4ED8"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF9F1239"/><name val="Calibri"/></font></fonts>
<fills count="10"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F6F4"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFE4E6"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="24"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="4" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="3" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="4" borderId="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"><alignment vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="4" borderId="1" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="165" fontId="0" fillId="4" borderId="1" applyNumberFormat="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="5" fillId="3" borderId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/><xf numFmtId="3" fontId="5" fillId="3" borderId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="3" fillId="2" borderId="1" applyNumberFormat="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="6" fillId="5" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="7" fillId="6" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="8" fillId="7" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="9" fillId="8" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="4" fillId="9" borderId="1" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="4" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="166" fontId="0" fillId="0" borderId="1" applyNumberFormat="1" applyBorder="1"/></cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>
XML;
    }
}
