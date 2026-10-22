<?php

namespace App\Console\Commands;

use App\Models\PhotoCapture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeFitPhotos extends Command
{
    protected $signature = 'fit-photos:purge';

    protected $description = 'Remove expired kiosk images and fitting metadata, retaining code/print audit records';

    public function handle(): int
    {
        $count = 0;
        PhotoCapture::where('storage_disk', 'fit-photos')->whereNull('purged_at')->where('expires_at', '<=', now('UTC'))
            ->chunkById(100, function ($captures) use (&$count) {
                foreach ($captures as $capture) {
                    DB::transaction(function () use ($capture, &$count) {
                        // Acquire a SQLite write lock before touching files, too.
                        PhotoCapture::whereKey($capture->id)->whereNull('purged_at')->update(['updated_at' => now()]);
                        $capture = PhotoCapture::lockForUpdate()->find($capture->id);
                        if (! $capture || $capture->purged_at) {
                            return;
                        }
                        foreach (array_filter([$capture->photo_path, $capture->thumbnail_path]) as $path) {
                            if (! Storage::disk('fit-photos')->delete($path)) {
                                throw new \RuntimeException('Could not delete expired photo.');
                            }
                        }
                        $capture->update([
                            'photo_path' => null, 'thumbnail_path' => null, 'request_hash' => null,
                            'gender' => null, 'activity' => null, 'background_id' => null, 'products' => null,
                            'image_mime' => null, 'image_size' => null, 'purged_at' => now('UTC'),
                        ]);
                        $count++;
                    });
                }
            });
        $this->info("Purged {$count} expired fitting photos.");

        return self::SUCCESS;
    }
}
