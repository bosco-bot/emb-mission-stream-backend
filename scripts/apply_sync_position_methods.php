#!/usr/bin/env php
<?php
/**
 * Script à exécuter sur le serveur pour ajouter getCurrentSyncPosition(), advanceSyncPosition()
 * et les helpers fichier dans WebTVAutoPlaylistService.php.
 *
 * Usage: sudo php scripts/apply_sync_position_methods.php
 * (depuis /var/www/emb-mission)
 */

$file = __DIR__ . '/../app/Services/WebTVAutoPlaylistService.php';
if (!is_file($file)) {
    $file = '/var/www/emb-mission/app/Services/WebTVAutoPlaylistService.php';
}
if (!is_readable($file)) {
    fwrite(STDERR, "Fichier non lisible: $file\n");
    exit(1);
}

$content = file_get_contents($file);

// Déjà présent ?
if (strpos($content, 'function advanceSyncPosition()') !== false) {
    echo "Les méthodes sont déjà présentes. Rien à faire.\n";
    exit(0);
}

// 1) Ajouter l'import File
$content = str_replace(
    "use Illuminate\Support\Facades\Http;\n",
    "use Illuminate\Support\Facades\Http;\nuse Illuminate\Support\Facades\File;\n",
    $content,
    $count
);
if ($count !== 1) {
    fwrite(STDERR, "Import File: motif non trouvé ou déjà présent.\n");
}

// 2) Insérer les méthodes avant "Vérifier le statut du live"
$needle = "        }\n    }\n    \n    /**\n     * Vérifier le statut du live avec cache pour réduire les appels\n     */\n    public function checkLiveStatus(): array";
$insert = "        }\n    }\n\n    private function getSyncPositionFilePath(int \$playlistId): string\n    {\n        return storage_path('app/webtv_sync_position_' . \$playlistId . '.json');\n    }\n\n    private function readSyncPositionFromFile(int \$playlistId): ?array\n    {\n        \$path = \$this->getSyncPositionFilePath(\$playlistId);\n        if (!File::isFile(\$path)) {\n            return null;\n        }\n        \$content = @file_get_contents(\$path);\n        if (\$content === false) {\n            return null;\n        }\n        \$data = @json_decode(\$content, true);\n        if (!is_array(\$data) || !isset(\$data['item_id'])) {\n            return null;\n        }\n        return \$data;\n    }\n\n    private function writeSyncPositionToFile(int \$playlistId, array \$position): void\n    {\n        \$path = \$this->getSyncPositionFilePath(\$playlistId);\n        \$dir = dirname(\$path);\n        if (!File::isDirectory(\$dir)) {\n            File::makeDirectory(\$dir, 0755, true);\n        }\n        @file_put_contents(\$path, json_encode(\$position, JSON_PRETTY_PRINT));\n    }\n\n    /**\n     * Position de sync globale pour le flux unifié (unified.m3u8).\n     * Utilisée par UnifiedStreamController (M3U) et commande webtv:advance-sync-position.\n     */\n    public function getCurrentSyncPosition(): array\n    {\n        try {\n            \$activePlaylist = WebTVPlaylist::where('is_active', true)->first();\n            if (!\$activePlaylist) {\n                return [\n                    'success' => false,\n                    'message' => 'Aucune playlist active',\n                    'current_item' => ['item_id' => null, 'current_time' => 0.0],\n                ];\n            }\n            \$cacheKey = 'webtv_unified_sync_position_' . \$activePlaylist->id;\n            \$cached = Cache::get(\$cacheKey);\n            if (!is_array(\$cached) || !isset(\$cached['item_id'])) {\n                \$cached = \$this->readSyncPositionFromFile(\$activePlaylist->id);\n            }\n            if (is_array(\$cached) && isset(\$cached['item_id'])) {\n                Cache::put(\$cacheKey, \$cached, now()->addHours(1));\n                return [\n                    'success' => true,\n                    'current_item' => [\n                        'item_id' => (int) \$cached['item_id'],\n                        'current_time' => (float) (\$cached['current_time'] ?? 0),\n                    ],\n                ];\n            }\n            \$firstItem = \$activePlaylist->items()\n                ->where('sync_status', 'synced')\n                ->orderBy('order')\n                ->first();\n            if (!\$firstItem) {\n                return [\n                    'success' => false,\n                    'message' => 'Aucun item dans la playlist',\n                    'current_item' => ['item_id' => null, 'current_time' => 0.0],\n                ];\n            }\n            \$default = ['item_id' => \$firstItem->id, 'current_time' => 0.0, 'updated_at' => time()];\n            Cache::put(\$cacheKey, \$default, now()->addHours(1));\n            \$this->writeSyncPositionToFile(\$activePlaylist->id, \$default);\n            return [\n                'success' => true,\n                'current_item' => ['item_id' => \$default['item_id'], 'current_time' => \$default['current_time']],\n            ];\n        } catch (\\Exception \$e) {\n            Log::error('getCurrentSyncPosition: ' . \$e->getMessage());\n            return [\n                'success' => false,\n                'message' => \$e->getMessage(),\n                'current_item' => ['item_id' => null, 'current_time' => 0.0],\n            ];\n        }\n    }\n\n    /**\n     * Avance la position de lecture du flux unifié selon le temps écoulé.\n     * Appelée par la commande webtv:advance-sync-position (planifiée chaque minute).\n     */\n    public function advanceSyncPosition(): array\n    {\n        try {\n            if (Cache::get('webtv_system_paused')) {\n                return ['success' => true, 'message' => 'Système en pause'];\n            }\n            if (\$this->checkLiveStatus()['is_live']) {\n                return ['success' => true, 'message' => 'Mode live'];\n            }\n\n            \$activePlaylist = WebTVPlaylist::where('is_active', true)->first();\n            if (!\$activePlaylist) {\n                return ['success' => false, 'message' => 'Aucune playlist active'];\n            }\n\n            \$cacheKey = 'webtv_unified_sync_position_' . \$activePlaylist->id;\n            \$cached = Cache::get(\$cacheKey);\n            if (!is_array(\$cached) || !isset(\$cached['item_id'])) {\n                \$cached = \$this->readSyncPositionFromFile(\$activePlaylist->id);\n            }\n\n            \$items = \$activePlaylist->items()\n                ->where('sync_status', 'synced')\n                ->orderBy('order')\n                ->get();\n\n            \$sequence = [];\n            foreach (\$items as \$item) {\n                if (\$item->ant_media_item_id && (\$item->duration ?? 0) > 0) {\n                    \$sequence[] = [\n                        'item_id' => \$item->id,\n                        'duration' => (float) \$item->duration,\n                    ];\n                }\n            }\n            if (empty(\$sequence)) {\n                return ['success' => false, 'message' => 'Aucun item VoD'];\n            }\n\n            \$now = time();\n            \$updatedAt = \$cached['updated_at'] ?? (\$now - 60);\n            \$elapsed = max(0, \$now - \$updatedAt);\n\n            \$currentItemId = isset(\$cached['item_id']) ? (int) \$cached['item_id'] : \$sequence[0]['item_id'];\n            \$currentTime = (float) (\$cached['current_time'] ?? 0);\n\n            \$index = 0;\n            foreach (\$sequence as \$i => \$seg) {\n                if (\$seg['item_id'] === \$currentItemId) {\n                    \$index = \$i;\n                    break;\n                }\n            }\n\n            \$currentTime += \$elapsed;\n            \$isLoop = (bool) \$activePlaylist->is_loop;\n\n            while (\$currentTime >= \$sequence[\$index]['duration']) {\n                \$currentTime -= \$sequence[\$index]['duration'];\n                \$index++;\n                if (\$index >= count(\$sequence)) {\n                    if (\$isLoop) {\n                        \$index = 0;\n                    } else {\n                        \$index = count(\$sequence) - 1;\n                        \$currentTime = \$sequence[\$index]['duration'];\n                        break;\n                    }\n                }\n            }\n\n            \$newItemId = \$sequence[\$index]['item_id'];\n            \$newTime = min(\$currentTime, \$sequence[\$index]['duration']);\n\n            \$newPosition = [\n                'item_id' => \$newItemId,\n                'current_time' => \$newTime,\n                'updated_at' => \$now,\n            ];\n            Cache::put(\$cacheKey, \$newPosition, now()->addHours(1));\n            \$this->writeSyncPositionToFile(\$activePlaylist->id, \$newPosition);\n\n            return ['success' => true, 'message' => 'Position mise à jour', 'item_id' => \$newItemId, 'current_time' => \$newTime];\n        } catch (\\Exception \$e) {\n            Log::error('advanceSyncPosition: ' . \$e->getMessage());\n            return ['success' => false, 'message' => \$e->getMessage()];\n        }\n    }\n\n    /**\n     * Vérifier le statut du live avec cache pour réduire les appels\n     */\n    public function checkLiveStatus(): array";

if (strpos($content, $needle) === false) {
    fwrite(STDERR, "Motif d'insertion non trouvé (checkLiveStatus). Vérifiez le fichier.\n");
    exit(1);
}

$content = str_replace($needle, $insert, $content);

if (!@file_put_contents($file, $content)) {
    fwrite(STDERR, "Impossible d'écrire le fichier. Exécutez avec sudo.\n");
    exit(1);
}

echo "OK. Méthodes getCurrentSyncPosition(), advanceSyncPosition() et helpers fichier ajoutés.\n";
echo "Vérifiez avec: php artisan webtv:advance-sync-position\n";
