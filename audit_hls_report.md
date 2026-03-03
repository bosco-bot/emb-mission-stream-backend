# Rapport d'Audit Technique : Streaming HLS & Architecture Web TV

**Date :** 19 Février 2026
**Statut :** Critique (Problèmes de stabilité confirmés)
**Objet :** Diagnostic des gels vidéo (freezes) sur le flux `unified.m3u8`

---

## 1. 🚨 Synthèse Exécutive
L'audit a révélé une **instabilité structurelle** dans la génération du flux HLS.
Le problème principal est une **rupture de continuité (Sequence Gap)** détectée lors de l'analyse en temps réel. Le flux "saute" parfois des segments (ex: passage brutal de la séquence 125833 à 125835), ce qui provoque un **freeze immédiat** chez le client (VLC, Smart TV) car le lecteur perd le fil temporel.

---

## 2. 🔍 Analyse du Flux HLS (`unified.m3u8`)

### ✅ Points Forts (Conformes RFC 8216)
*   **Structure du Manifeste :** Valide (EXTM3U, VERSION:3).
*   **Target Duration :** 3s (Correct pour des segments de ~2.08s).
*   **Accessibilité :** Les segments (.ts) sont bien accessibles via HTTP 200.

### ❌ Problèmes Critiques Détectés
*   **Rupture de Séquence (SEQUENCE GAP) :**
    *   *Observation :* Lors du test à 21:55:27, le flux a sauté de la séquence **125833** à **125835**.
    *   *Impact :* Les lecteurs (surtout Smart TV et VLC) détestent ça. Ils attendent le 125834, ne le voient jamais, et gèlent en attendant de se recalibrer.
    *   *Cause probable :* Le script de génération (`RunUnifiedStream`) ou le contrôleur ne cadence pas assez vite ou "écrase" une itération sous la charge.

*   **Micro-Caching Nginx Inadapté :**
    *   Le cache de 1s sur le manifeste (`expires 1s`) peut servir une version périmée pendant qu'une nouvelle est générée, augmentant le risque de "ratés" pour le client qui poll très vite.

---

## 3. 🏗️ Analyse de l'Architecture (Codebase)

### Flux VOD (Virtuel)
*   Le système simule du "Direct" à partir de fichiers VOD.
*   **Risque identifié :** La classe `UnifiedHlsBuilder` utilise une fenêtre glissante (`windowSize = 25`). Si le serveur met trop de temps à calculer la timeline VOD (boucles complexes, accès BDD lent), la génération prend du retard.
*   **Point Noir :** Le script de génération tourne en boucle infinie (`while(true)`) avec un `sleep(2)`. Si le `build()` prend 1.5s (accès disque/BDD), le cycle total fait 3.5s. Or, les segments ne durent que **2s**.
    *   **Conséquence :** Le serveur ne génère pas la playlist assez vite pour suivre le rythme de la vidéo. **C'est la cause mathématique des freezes.**

### Flux Live (Ant Media)
*   Le contrôleur (`UnifiedStreamController`) sert parfois directement le fichier d'Ant Media via `serveLivePlaylistDirectly`.
*   C'est une bonne approche (bypass du builder PHP lent), **MAIS** la logique de transition (VOD <-> Live) repose sur des caches partagés qui peuvent être désynchronisés.

---

## 4. 📱 Compatibilité Multi-Plateforme

| Plateforme | Statut | Risque |
| :--- | :--- | :--- |
| **VLC** | ⚠️ Instable | Très sensible aux trous de séquence. Freeze quasi garanti lors des gaps. |
| **Smart TV / Android TV** | ⚠️ Critique | Le buffer est souvent faible. Un retard de génération = écran noir. |
| **iOS / Safari** | 🟠 Moyen | Gère mieux les erreurs mais risque de redémarrer le flux en boucle. |
| **Web (HLS.js)** | 🟢 Correct | Le mécanisme de "recover" masquait le problème jusqu'ici. |

---

## 5. 🛠️ Plan d'Attaque (Recommandations)

Pour stabiliser le flux sans tout réécrire, voici les actions correctives par ordre de priorité :

### Étape 1 : Optimiser la Cadence de Génération (URGENT)
Le générateur PHP est trop lent par rapport à la durée des segments.
*   **Action :** Réduire le `sleep` de la commande artisan de `2s` à `1s` (ou `usleep(500000)`).
*   **Action :** Optimiser `UnifiedHlsBuilder` pour ne pas reconstruire la playlist si rien n'a changé (vérification de hash/timestamp plus légère).

### Étape 2 : Lisser la "Target Duration"
*   **Action :** Augmenter légèrement la `TARGETDURATION` à **4s** dans le manifeste (même si les segments font 2s). Cela donne une "marge de respiration" aux lecteurs pour ne pas timeout dès qu'un segment a 100ms de retard.

### Étape 3 : Verrouiller la Continuité (Sequence Guard)
*   **Action :** Renforcer le mécanisme de sauvegarde de `media_sequence` dans `UnifiedHlsBuilder`. Un sytème de "lock" doit empêcher de publier une playlist si la séquence n'est pas strictement `N+1`.

### Étape 4 : Désactiver le Micro-Cache Nginx pour le M3U8
*   **Action :** Le fichier `unified.m3u8` ne doit **JAMAIS** être mis en cache par Nginx, même 1 seconde. C'est PHP qui doit décider.
*   **Modif :** `Cache-Control: no-store, no-cache, private` (déjà fait en partie PHP, à confirmer côté Nginx).

---

## 6. Conclusion
Le problème n'est pas "magique", il est **arythmique**.
Votre serveur génère la playlist un peu moins vite que la vitesse de lecture de la vidéo. Au bout de quelques minutes, le lecteur a "rattrapé" le générateur, tombe sur un vide, et freeze.

**Feu vert pour appliquer les correctifs ?**
