<?php

namespace App\Http\Requests;

use App\ExpenseCategory;
use App\FinanceType;
use App\TripFinance;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreFinanceEntryRequest extends FormRequest
{
    private const MAX_RECEIPT_BYTES = 2097152;

    private ?string $decodedReceipt = null;

    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('trip'));

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $isLeader = $this->user()->role === UserRole::TourLeader;

        return [
            'type' => ['required', $isLeader ? Rule::in(['expense']) : Rule::enum(FinanceType::class)],
            'category' => ['exclude_unless:type,expense', 'required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:'.TripFinance::MAX_AMOUNT],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'],
            'leader_id' => [$isLeader ? 'exclude' : 'nullable', 'required_if:type,advance,return,reimbursement', 'integer'],
            'receipt' => $isLeader
                ? ['exclude']
                : ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'receipt_data' => [$isLeader ? 'required' : 'exclude', 'string', 'max:2800000'],
            'captured_at' => [$isLeader ? 'required' : 'nullable', 'date', 'before_or_equal:now'],
            'latitude' => [$isLeader ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$isLeader ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => [$isLeader ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100000'],
            'receipt_original_size' => [$isLeader ? 'required' : 'nullable', 'integer', 'min:1', 'max:52428800'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.integer' => 'Gunakan nominal rupiah bulat tanpa titik atau koma.',
            'amount.min' => 'Nominal harus lebih dari nol.',
            'receipt_data.required' => 'Ambil foto bukti melalui kamera sebelum menyimpan pengajuan.',
            'receipt_data.max' => 'Foto hasil kompresi maksimal 2 MB. Ambil ulang foto jika proses kompresi gagal.',
            'captured_at.required' => 'Waktu pengambilan foto belum tersedia. Aktifkan JavaScript lalu ambil ulang foto.',
            'latitude.required' => 'Lokasi wajib disertakan. Izinkan akses lokasi pada browser.',
            'longitude.required' => 'Lokasi wajib disertakan. Izinkan akses lokasi pada browser.',
            'occurred_on.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user()->role !== UserRole::TourLeader || $validator->errors()->has('receipt_data')) {
                return;
            }

            $dataUrl = $this->string('receipt_data')->toString();
            $prefix = 'data:image/jpeg;base64,';
            $decoded = str_starts_with($dataUrl, $prefix)
                ? base64_decode(substr($dataUrl, strlen($prefix)), true)
                : false;
            $image = $decoded === false ? false : @getimagesizefromstring($decoded);

            if ($decoded === false || $image === false || ($image['mime'] ?? null) !== 'image/jpeg') {
                $validator->errors()->add('receipt_data', 'Foto kamera tidak dapat diverifikasi. Ambil ulang foto dan tunggu sampai proses selesai.');

                return;
            }
            if (strlen($decoded) > self::MAX_RECEIPT_BYTES) {
                $validator->errors()->add('receipt_data', 'Foto hasil kompresi maksimal 2 MB. Ambil ulang foto.');

                return;
            }

            $this->decodedReceipt = $decoded;
        }];
    }

    public function receiptContents(): string
    {
        if ($this->decodedReceipt === null) {
            throw new LogicException('Validated camera receipt is unavailable.');
        }

        return $this->decodedReceipt;
    }
}
