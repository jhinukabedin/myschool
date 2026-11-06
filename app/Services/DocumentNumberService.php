<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Generate the next document number atomically. Sequence rows are locked so
     * concurrent requests cannot produce the same receipt/admission number.
     */
    public function next(string $key, string $prefix, int $pad = 5): string
    {
        return DB::transaction(function () use ($key, $prefix, $pad) {
            DB::table('document_sequences')->insertOrIgnore([
                'key' => $key,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->last_number + 1;

            DB::table('document_sequences')->where('key', $key)->update([
                'last_number' => $number,
                'updated_at' => now(),
            ]);

            return $prefix.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @deprecated Student IDs now use StudentCodeService (YY+CLASS_CODE+SERIAL).
     * Kept for audit/history compatibility only — new admissions do not use it.
     */
    public function admissionNumber(int $year): string
    {
        return $this->next('admission:'.$year, 'ADM-'.$year.'-', 4);
    }

    public function enquiryNumber(int $year): string
    {
        return $this->next('enquiry:'.$year, 'ENQ-'.$year.'-', 4);
    }

    public function receiptNumber(int $year): string
    {
        return $this->next('receipt:'.$year, 'RCP-'.$year.'-', 5);
    }

    public function employeeNumber(int $year): string
    {
        return $this->next('employee:'.$year, 'EMP-'.$year.'-', 3);
    }
}
