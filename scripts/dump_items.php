<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\WebTVPlaylistItem;
$ids = array_slice($argv, 1);
if (!$ids) { fwrite(STDERR, "Usage: php dump_items.php <video_file_id> [more ids]\n"); exit(1); }
foreach ($ids as $vid) {
    $vid = (int)$vid;
    echo "-- video_file_id={$vid} --\n";
    $items = WebTVPlaylistItem::where('video_file_id',$vid)->get(['id','video_file_id','sync_status','ant_media_item_id']);
    if ($items->isEmpty()) { echo "(aucun item)\n"; continue; }
    foreach ($items as $it) {
        $ant = $it->ant_media_id ?? $it->ant_media_item_id; // compat
        echo "item={$it->id} file={$it->video_file_id} status={$it->sync_status} ant=" . ($ant ?: 'NULL') . "\n";
    }
}
