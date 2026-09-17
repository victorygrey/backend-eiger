<?php

namespace App\Console\Commands;

use App\Services\PimInboundTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class IssuePimTokenCommand extends Command
{
    protected $signature = 'pim:issue-token {--ttl=120 : Masa berlaku dalam menit (maksimal 10080)} {--expires-at= : Waktu kedaluwarsa ISO dalam timezone aplikasi} {--name=EIGER-PIM : Nama pemakai token} {--json : Keluarkan JSON}';

    protected $description = 'Membuat Bearer token sementara untuk endpoint inbound PIM';

    public function handle(PimInboundTokenService $tokens): int
    {
        $issued = $this->option('expires-at')
            ? $tokens->issueUntil((string) $this->option('name'), Carbon::parse((string) $this->option('expires-at'), config('app.timezone')))
            : $tokens->issue((string) $this->option('name'), (int) $this->option('ttl'));
        if ($this->option('json')) {
            $this->line(json_encode($issued, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Bearer token PIM berhasil dibuat. Token hanya ditampilkan sekali.');
            $this->line('Token: '.$issued['token']);
            $this->line('Berlaku sampai: '.$issued['expires_at']);
        }

        return self::SUCCESS;
    }
}
