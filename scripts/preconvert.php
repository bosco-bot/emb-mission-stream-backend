<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MediaFile;
use App\Services\AntMediaVoDService;

$ids = array_slice($argv, 1);
if (empty($ids)) {
    fwrite(STDERR, "Usage: php preconvert.php <mediaId> [<mediaId> ...]\n");
    exit(1);
}
$svc = new AntMediaVoDService();
foreach ($ids as $id) {
    $id = (int)$id;
    $mf = MediaFile::find($id);
    if (!$mf) { echo "#$id introuvable\n"; continue; }
    if ($mf->file_type !== 'video') { echo "#$id pas video\n"; continue; }
    echo "Pré-conversion #$id ({$mf->original_name})... ";
    $res = $svc->createVodForMediaFile($mf);
    echo (($res['success'] ?? false) ? 'OK' : 'ECHEC') . ' - ' . ($res['message'] ?? '') . "\n";
}
