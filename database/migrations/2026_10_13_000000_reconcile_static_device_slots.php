<?php

use App\Models\FitAndGoDevice;
use App\Models\Tablet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->reconcileKiosks();
        $this->reconcileTablets();
    }

    private function reconcileKiosks(): void
    {
        $devices = DB::table('fit_and_go_devices')->orderBy('id')->get();
        $assignments = $this->assignExistingRows(
            $devices,
            FitAndGoDevice::STATIC_SLOTS,
            'device_code',
            fn (object $device, int $floor): bool => preg_match(
                '/(?:lantai|floor|lt)[\s._-]*'.preg_quote((string) $floor, '/').'(?:\D|$)/i',
                implode(' ', [$device->name, $device->device_code, $device->location])
            ) === 1,
        );

        $now = now();
        foreach ($assignments as $floor => $device) {
            if ($device) {
                DB::table('fit_and_go_devices')->where('id', $device->id)->update([
                    'device_code' => '__fit_static_'.$floor.'_'.$device->id,
                    'updated_at' => $now,
                ]);
            }
        }

        $staticIds = [];
        foreach (FitAndGoDevice::STATIC_SLOTS as $floor => $slot) {
            $device = $assignments[$floor];
            if ($device) {
                $staticIds[] = $device->id;
                DB::table('fit_and_go_devices')->where('id', $device->id)->update([
                    ...$slot,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $activationCode = 'FIT-L'.$floor.'-'.Str::upper(Str::random(8));
            $staticIds[] = DB::table('fit_and_go_devices')->insertGetId([
                ...$slot,
                'status' => 'offline',
                'is_active' => true,
                'product_selection_mode' => 'selected',
                'activation_code_hash' => Hash::make($activationCode),
                'activation_code_encrypted' => Crypt::encryptString($activationCode),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('fit_and_go_devices')->whereNotIn('id', $staticIds)->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);
    }

    private function reconcileTablets(): void
    {
        $tablets = DB::table('tablets')->orderBy('id')->get();
        $assignments = $this->assignExistingRows(
            $tablets,
            Tablet::STATIC_SLOTS,
            'slug',
            fn (object $tablet, int $slot): bool => preg_match(
                '/(?:tablet|display|meja)[\s._-]*'.preg_quote((string) $slot, '/').'(?:\D|$)/i',
                implode(' ', [$tablet->name, $tablet->slug, $tablet->location])
            ) === 1,
        );

        $now = now();
        foreach ($assignments as $slot => $tablet) {
            if ($tablet) {
                DB::table('tablets')->where('id', $tablet->id)->update([
                    'slug' => '__tablet_static_'.$slot.'_'.$tablet->id,
                    'updated_at' => $now,
                ]);
            }
        }

        $staticIds = [];
        foreach (Tablet::STATIC_SLOTS as $slot => $identity) {
            $tablet = $assignments[$slot];
            if ($tablet) {
                $staticIds[] = $tablet->id;
                DB::table('tablets')->where('id', $tablet->id)->update([
                    ...$identity,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $activationCode = 'TABLET-'.$slot.'-'.Str::upper(Str::random(8));
            $staticIds[] = DB::table('tablets')->insertGetId([
                ...$identity,
                'location' => null,
                'activation_code_hash' => Hash::make($activationCode),
                'activation_code_lookup_hash' => hash_hmac(
                    'sha256',
                    Tablet::normalizeActivationCode($activationCode),
                    (string) config('app.key'),
                ),
                'activation_code_encrypted' => Crypt::encryptString($activationCode),
                'config_version' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('tablets')->whereNotIn('id', $staticIds)->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);
    }

    /**
     * Prefer rows that already use a fixed identifier, then a matching floor/slot,
     * and finally the oldest unclaimed row so existing relations remain attached.
     *
     * @param  array<int, array<string, string>>  $definitions
     * @return array<int, object|null>
     */
    private function assignExistingRows(
        Collection $rows,
        array $definitions,
        string $identifierColumn,
        callable $matchesSlot,
    ): array {
        $claimed = [];
        $assignments = [];

        foreach ($definitions as $slot => $definition) {
            $row = $rows->first(fn (object $candidate): bool =>
                ! in_array($candidate->id, $claimed, true)
                && $candidate->{$identifierColumn} === $definition[$identifierColumn]
            );
            $row ??= $rows->first(fn (object $candidate): bool =>
                ! in_array($candidate->id, $claimed, true)
                && $matchesSlot($candidate, $slot)
            );
            $row ??= $rows->first(fn (object $candidate): bool => ! in_array($candidate->id, $claimed, true));

            $assignments[$slot] = $row;
            if ($row) {
                $claimed[] = $row->id;
            }
        }

        return $assignments;
    }

    public function down(): void
    {
        // The migration preserves existing row IDs and relations. Reverting the
        // former arbitrary identities cannot be done safely or deterministically.
    }
};
