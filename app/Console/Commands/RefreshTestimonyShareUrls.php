<?php

namespace App\Console\Commands;

use App\Models\Testimony;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshTestimonyShareUrls extends Command
{
    protected $signature = 'testimonies:refresh-share-urls';

    protected $description = 'Recalcule le lien de partage (share_url) de tous les témoignages à partir de APP_URL';

    public function handle(): int
    {
        $this->info('Base des liens : ' . rtrim(config('app.url'), '/'));

        $count = 0;
        DB::table('testimonies')->select('id')->orderBy('id')->chunk(500, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                DB::table('testimonies')->where('id', $row->id)->update(['share_url' => Testimony::shareUrlFor($row->id)]);
                $count++;
            }
        });

        $this->info("{$count} lien(s) de partage mis à jour.");

        return self::SUCCESS;
    }
}
