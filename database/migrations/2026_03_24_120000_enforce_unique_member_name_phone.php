<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('members')->orderBy('id')->get();
            $seen = [];

            foreach ($rows as $row) {
                $name = $this->normalizeName($row->name);
                $phone = $this->normalizePhone($row->phone);
                $key = strtolower($name) . '|' . ($phone ?? '');

                if (!isset($seen[$key])) {
                    DB::table('members')->where('id', $row->id)->update([
                        'name' => $name,
                        'phone' => $phone,
                        'updated_at' => now(),
                    ]);
                    $seen[$key] = (int) $row->id;
                    continue;
                }

                $keepId = $seen[$key];
                $dropId = (int) $row->id;

                DB::table('member_financials')->where('member_id', $dropId)->update(['member_id' => $keepId]);
                DB::table('payments')->where('member_id', $dropId)->update(['member_id' => $keepId]);
                DB::table('welfare_events')->where('member_id', $dropId)->update(['member_id' => $keepId]);
                DB::table('members')->where('id', $dropId)->delete();
            }
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->unique(['name', 'phone'], 'members_name_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropUnique('members_name_phone_unique');
        });
    }

    private function normalizeName(?string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $name));
        return $name === '' ? $name : mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizePhone(?string $phone): ?string
    {
        $clean = preg_replace('/[^0-9+]/', '', (string) $phone);
        return strlen($clean) >= 9 ? $clean : null;
    }
};

