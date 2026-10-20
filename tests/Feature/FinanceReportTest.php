<?php

namespace Tests\Feature;

use App\Models\BudgetItem;
use App\Models\Customer;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PharData;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_report_totals_include_all_filtered_trips_not_only_current_page(): void
    {
        $customer = Customer::factory()->create();
        $trips = Trip::factory()->count(16)->for($customer)->create(['contract_amount' => 1000000, 'start_date' => '2026-09-01', 'end_date' => '2026-09-02']);
        $admin = User::factory()->superAdmin()->create();
        foreach ($trips as $trip) {
            FinanceEntry::factory()->for($trip)->create(['created_by' => $admin->id, 'type' => 'payment', 'amount' => 500000]);
            FinanceEntry::factory()->for($trip)->create(['created_by' => $admin->id, 'type' => 'expense', 'amount' => 100000]);
        }

        $response = $this->actingAs($admin)->get(route('super-admin.finance.index', ['from' => '2026-09-01', 'to' => '2026-09-30']));

        $response->assertSee('Rekapitulasi keuangan')->assertSee('Penerimaan dan biaya per bulan trip')->assertViewHas('totals', function ($totals): bool {
            return (int) $totals->trips === 16 && (int) $totals->contracts === 16000000
                && (int) $totals->payments === 8000000 && (int) $totals->expenses === 1600000
                && (int) $totals->profit === 14400000 && (int) $totals->receivables === 8000000
                && (int) $totals->cash === 6400000;
        });
        $response->assertViewHas('trips', fn ($page): bool => $page->count() === 15 && $page->total() === 16);
    }

    public function test_report_filters_trip_start_date_status_and_search_consistently(): void
    {
        Trip::factory()->create(['name' => 'Target Bandung', 'status' => 'confirmed', 'start_date' => '2026-09-01', 'contract_amount' => 123456]);
        Trip::factory()->create(['name' => 'Hidden Other', 'status' => 'draft', 'start_date' => '2026-08-01', 'contract_amount' => 999999]);
        $filters = ['search' => 'Bandung', 'status' => 'confirmed', 'from' => '2026-09-01', 'to' => '2026-09-30'];
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('super-admin.finance.index', $filters))->assertSee('Target Bandung')->assertDontSee('Hidden Other')
            ->assertViewHas('totals', fn ($totals): bool => (int) $totals->contracts === 123456);
        $csv = $this->get(route('super-admin.finance.export', $filters))->assertDownload()->streamedContent();

        $this->assertStringContainsString('Target Bandung', $csv);
        $this->assertStringNotContainsString('Hidden Other', $csv);
    }

    public function test_empty_report_and_unknown_contract_are_not_fabricated_as_profit(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('super-admin.finance.index'))->assertSee('Belum ada trip sesuai filter');
        $trip = Trip::factory()->create();
        FinanceEntry::factory()->for($trip)->create(['amount' => 100000]);
        $this->get(route('super-admin.finance.index'))->assertViewHas('totals', fn ($totals): bool => (int) $totals->missing_contracts === 1 && (int) $totals->profit === 0)
            ->assertSee('Belum diisi');
    }

    public function test_report_excludes_pending_rejected_and_void_and_does_not_double_count_tl_costs(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 1000000]);
        $leader = User::factory()->tourLeader()->create();
        $admin = User::factory()->superAdmin()->create();
        foreach ([
            ['payment', 1000000, null, 'approved'], ['advance', 400000, $leader->id, 'approved'],
            ['expense', 300000, $leader->id, 'approved'], ['return', 100000, $leader->id, 'approved'],
            ['expense', 100000, null, 'approved'], ['expense', 999999, null, 'pending'],
            ['expense', 999999, null, 'rejected'], ['expense', 999999, null, 'void'],
        ] as [$type, $amount, $leaderId, $status]) {
            FinanceEntry::factory()->for($trip)->create(['created_by' => $admin->id, 'type' => $type, 'amount' => $amount, 'leader_id' => $leaderId, 'status' => $status]);
        }

        $this->actingAs($admin)->get(route('super-admin.finance.index'))->assertViewHas('totals', fn ($totals): bool => (int) $totals->expenses === 400000 && (int) $totals->cash === 600000 && (int) $totals->profit === 600000 && (int) $totals->pending_count === 1);
    }

    public function test_csv_exports_all_rows_and_escapes_spreadsheet_formulas(): void
    {
        $customer = Customer::factory()->create(['name' => '+SUM(1,2)']);
        Trip::factory()->count(16)->for($customer)->create(['name' => '=1+1', 'contract_amount' => 500000]);
        $this->actingAs(User::factory()->superAdmin()->create());

        $response = $this->get(route('super-admin.finance.export'));
        $csv = $response->assertDownload()->streamedContent();

        $this->assertSame(16, substr_count($csv, "'=1+1"));
        $this->assertSame(16, substr_count($csv, "'+SUM(1,2)"));
        $this->assertStringContainsString('sep=;', $csv);
        $this->assertStringContainsString('Nilai Kontrak', $csv);
        $this->assertStringContainsString('Rp 500.000', $csv);
    }

    public function test_invalid_filters_are_rejected_and_to_only_filter_is_supported(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('super-admin.finance.index', ['from' => '2026-09-30', 'to' => '2026-09-01', 'status' => 'fake']))->assertSessionHasErrors(['to', 'status']);
        $this->get(route('super-admin.finance.export', ['from' => 'bad-date']))->assertSessionHasErrors('from');
        $this->get(route('super-admin.finance.index', ['to' => '2026-09-30']))->assertOk();
    }

    public function test_print_mode_has_a_mobile_fallback_control(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('super-admin.finance.index', ['print' => 1]))
            ->assertSee('data-auto-print', false)
            ->assertSee('Buka dialog cetak')
            ->assertSee('Bagikan → Simpan ke File');
    }

    public function test_csv_contains_localized_detailed_transaction_and_review_metadata(): void
    {
        $admin = User::factory()->superAdmin()->create(['name' => 'Admin Finance']);
        $leader = User::factory()->tourLeader()->create(['name' => 'TL Papua']);
        $trip = Trip::factory()->create(['contract_amount' => 900000]);
        FinanceEntry::factory()->for($trip)->create([
            'created_by' => $leader->id,
            'leader_id' => $leader->id,
            'amount' => 125000,
            'occurred_on' => '2026-09-01',
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-09-02 10:30:00',
            'review_reason' => 'Bukti tidak lengkap',
            'captured_at' => '2026-09-01 12:34:56',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'location_accuracy' => 5,
            'receipt_original_size' => 2048000,
            'receipt_stored_size' => 512000,
        ]);

        $csv = $this->actingAs($admin)->get(route('super-admin.finance.export'))->assertDownload()->streamedContent();

        $this->assertStringContainsString('Tanggal Transaksi', $csv);
        $this->assertStringContainsString('01/09/2026', $csv);
        $this->assertStringContainsString('Rp 125.000', $csv);
        $this->assertStringContainsString('Bukti tidak lengkap', $csv);
        $this->assertStringContainsString('GPS -6.2000000, 106.8166667', $csv);
    }

    public function test_excel_export_downloads_a_real_xlsx_with_summary_and_transaction_sheets(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 30));
        $admin = User::factory()->superAdmin()->create();
        $trip = Trip::factory()->create(['name' => '=1+1', 'contract_amount' => 900000]);
        FinanceEntry::factory()->for($trip)->create([
            'created_by' => $admin->id,
            'status' => 'rejected',
            'amount' => 125000,
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-09-02 10:30:00',
            'review_reason' => 'Bukti tidak lengkap',
        ]);

        $response = $this->actingAs($admin)->get(route('super-admin.finance.excel'));
        $binary = $response->assertDownload('rekap-keuangan-arfaka-2026-10-02-093000.xlsx')->streamedContent();
        $parts = $this->xlsxParts($binary);
        $workbook = new DOMDocument;

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertSame('PK', substr($binary, 0, 2));
        $this->assertTrue($workbook->loadXML($parts['xl/workbook.xml']));
        $this->assertSame(['Ringkasan Keuangan', 'Detail Transaksi'], array_map(
            fn ($sheet): string => $sheet->getAttribute('name'),
            iterator_to_array($workbook->getElementsByTagName('sheet')),
        ));
        $this->assertStringContainsString('formatCode="&quot;Rp&quot; #,##0', $parts['xl/styles.xml']);
        $this->assertStringContainsString('<v>900000</v>', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('t="inlineStr"', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('=1+1', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringNotContainsString('Pencarian</t>', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('Bukti tidak lengkap', $parts['xl/worksheets/sheet2.xml']);
        $this->assertStringContainsString('<autoFilter', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('state="frozen"', $parts['xl/worksheets/sheet2.xml']);
    }

    public function test_excel_export_applies_search_date_and_status_filters_and_keeps_finance_totals(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $selected = Trip::factory()->create(['name' => 'Papua Utama', 'status' => 'closed', 'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'contract_amount' => 1000000]);
        Trip::factory()->create(['name' => 'Bandung Lain', 'status' => 'closed', 'start_date' => '2026-09-10', 'contract_amount' => 7000000]);
        Trip::factory()->create(['name' => 'Papua Terlalu Awal', 'status' => 'closed', 'start_date' => '2026-08-10', 'contract_amount' => 8000000]);
        Trip::factory()->create(['name' => 'Papua Terlalu Akhir', 'status' => 'closed', 'start_date' => '2026-10-10', 'contract_amount' => 8500000]);
        Trip::factory()->create(['name' => 'Papua Masih Draft', 'status' => 'draft', 'start_date' => '2026-09-10', 'contract_amount' => 9000000]);
        FinanceEntry::factory()->for($selected)->create(['type' => 'payment', 'status' => 'approved', 'amount' => 500000]);
        FinanceEntry::factory()->for($selected)->create(['type' => 'expense', 'status' => 'approved', 'amount' => 100000]);
        FinanceEntry::factory()->for($selected)->create(['type' => 'expense', 'status' => 'rejected', 'amount' => 999999]);
        BudgetItem::factory()->for($selected)->create(['quantity' => 2, 'unit_amount' => 25000]);
        $filters = ['search' => 'Papua', 'from' => '2026-09-01', 'to' => '2026-09-30', 'status' => 'closed'];
        $this->actingAs($admin);

        $dashboard = $this->get(route('super-admin.finance.index', $filters));
        $response = $this->get(route('super-admin.finance.excel', $filters));
        $parts = $this->xlsxParts($response->assertDownload()->streamedContent());
        $summary = $parts['xl/worksheets/sheet1.xml'];
        $details = $parts['xl/worksheets/sheet2.xml'];

        $dashboard->assertSee(route('super-admin.finance.excel', $filters))
            ->assertViewHas('totals', fn ($totals): bool => (int) $totals->contracts === 1000000
            && (int) $totals->payments === 500000 && (int) $totals->expenses === 100000
            && (int) $totals->receivables === 500000 && (int) $totals->profit === 900000);
        $this->assertStringContainsString($selected->code, $summary);
        $this->assertSame(1, substr_count($summary, $selected->code));
        $this->assertStringContainsString('Papua Utama', $summary);
        $this->assertStringContainsString('Periode mulai trip', $summary);
        $this->assertStringContainsString('Status trip', $summary);
        $this->assertStringContainsString('Pencarian', $summary);
        $this->assertStringNotContainsString('Bandung Lain', $summary);
        $this->assertStringNotContainsString('Papua Terlalu Awal', $summary);
        $this->assertStringNotContainsString('Papua Terlalu Akhir', $summary);
        $this->assertStringNotContainsString('Papua Masih Draft', $summary);
        $this->assertStringContainsString('<v>1000000</v>', $summary);
        $this->assertStringContainsString('<v>500000</v>', $summary);
        $this->assertStringContainsString('<v>100000</v>', $summary);
        $this->assertStringContainsString('<v>50000</v>', $summary);
        $this->assertStringContainsString('<v>900000</v>', $summary);
        $this->assertSame(3, substr_count($details, $selected->code));
        $this->assertStringContainsString('Ditolak', $details);
    }

    /** @return array<string, string> */
    private function xlsxParts(string $binary): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'arfaka-xlsx-');
        $zipPath = $temporaryPath.'.zip';
        rename($temporaryPath, $zipPath);
        file_put_contents($zipPath, $binary);
        try {
            $archive = new PharData($zipPath);
            $parts = [];
            foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $path) {
                $parts[$path] = $archive[$path]->getContent();
                $this->assertTrue((new DOMDocument)->loadXML($parts[$path]));
            }

            return $parts;
        } finally {
            unset($archive);
            unlink($zipPath);
        }
    }

    public function test_print_report_includes_all_filtered_trips_and_transaction_details(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trips = Trip::factory()->count(16)->create(['status' => 'confirmed', 'start_date' => '2026-09-01']);
        Trip::factory()->create(['name' => 'Outside filter', 'status' => 'draft']);
        FinanceEntry::factory()->for($trips->first())->create([
            'created_by' => $admin->id, 'status' => 'rejected', 'review_reason' => 'Bukti kurang jelas',
        ]);

        $response = $this->actingAs($admin)->get(route('super-admin.finance.index', ['print' => 1, 'status' => 'confirmed']));

        $response->assertOk()->assertSee('arfaka-logo.png')->assertSee('Bukti kurang jelas')
            ->assertSee('Ringkasan per trip')->assertSee('Rincian transaksi')->assertDontSee('Outside filter');
        foreach ($trips as $trip) {
            $response->assertSee($trip->code);
        }
    }
}
