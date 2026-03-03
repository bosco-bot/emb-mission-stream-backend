<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🔔 WEBHOOK ANT MEDIA SERVER
 *
 * Reçoit les notifications push d'Ant Media lors du démarrage ou de l'arrêt
 * d'un stream live. Invalide immédiatement le cache de détection live pour
 * permettre une transition Live↔VoD en < 3s (vs ≤ 30s avec polling seul).
 *
 * Sécurité :
 *  - Accès limité à localhost (127.0.0.1) uniquement → filtré par Nginx
 *  - Pas de données sensibles exposées dans la réponse
 *
 * Config Ant Media : listenerHookURL=http://127.0.0.1/api/webhooks/antmedia
 */
class AntMediaWebhookController extends Controller
{
    /**
     * Actions Ant Media déclenchant une invalidation du cache live.
     * Référence : https://antmedia.io/docs/developer-guide/webhooks/
     */
    private const LIVE_START_ACTIONS = [
        'liveStreamStarted',
        'broadcastStarted',
    ];

    private const LIVE_END_ACTIONS = [
        'liveStreamEnded',
        'broadcastFinished',
    ];

    /**
     * POST /api/webhooks/antmedia
     *
     * Corps JSON attendu d'Ant Media :
     * {
     *   "action":   "liveStreamStarted" | "liveStreamEnded" | ...,
     *   "streamId": "hG0h4wperA85Dxcs...",
     *   "streamName": "..."
     * }
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // ── Sécurité : localhost uniquement ──────────────────────────────────
        // Nginx doit bloquer toute requête externe avant d'arriver ici.
        // Cette vérification est une ligne de défense supplémentaire.
        $ip = $request->ip();
        if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
            Log::warning('🚫 Webhook Ant Media bloqué - IP non autorisée', ['ip' => $ip]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $action   = $request->input('action', '');
        $streamId = $request->input('streamId', '');

        Log::info('🔔 Webhook Ant Media reçu', [
            'action'   => $action,
            'streamId' => $streamId,
        ]);

        if (in_array($action, self::LIVE_START_ACTIONS, true)) {
            $this->onLiveStarted($streamId);
        } elseif (in_array($action, self::LIVE_END_ACTIONS, true)) {
            $this->onLiveEnded($streamId);
        } else {
            // Action non gérée (ex: vodStreamCreated) — on ignore silencieusement
            Log::debug('🔔 Webhook Ant Media : action ignorée', ['action' => $action]);
        }

        // Ant Media attend un 200 pour considérer le webhook comme livré
        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Live démarre → invalider le cache de détection immédiatement.
     * Le prochain poll de checkLiveStatus() fera un appel Ant Media frais
     * et détectera le live en < 1s.
     */
    private function onLiveStarted(string $streamId): void
    {
        // Invalider les caches de statut live (polling reprendra frais)
        Cache::forget('live_status_check');
        Cache::forget('live_status_last_confirmed');

        // Invalider le cache de contexte (clé roulante toutes les 2s)
        $this->forgetContextCache();

        Log::info('✅ Cache live invalidé — DÉMARRAGE du live détecté via webhook', [
            'stream_id' => $streamId,
        ]);
    }

    /**
     * Live s'arrête → invalider le cache + effacer l'hystérésis pour
     * ne pas attendre 10s avant de basculer en VoD.
     */
    private function onLiveEnded(string $streamId): void
    {
        Cache::forget('live_status_check');
        Cache::forget('live_status_last_confirmed');

        // Invalider le cache de contexte (clé roulante toutes les 2s)
        $this->forgetContextCache();

        Log::info('✅ Cache live invalidé — FIN du live détectée via webhook', [
            'stream_id' => $streamId,
        ]);
    }

    /**
     * Invalide la clé de cache roulante de getCurrentPlaybackContext().
     * La clé est floor(time() / 2) → deux clés possibles autour de la seconde courante.
     */
    private function forgetContextCache(): void
    {
        $now = time();
        Cache::forget('playback_context_v2_' . floor($now / 2));
        Cache::forget('playback_context_v2_' . floor(($now - 1) / 2));
    }
}
