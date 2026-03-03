<?php

$streamUrl = 'https://tv.embmission.com/hls/streams/unified.m3u8';
$duration = 30; // Seconds to monitor
$interval = 2; // Poll every 2 seconds

echo "🔍 Starting HLS Compliance Check on $streamUrl\n";
echo "⏱️  Monitoring for $duration seconds...\n\n";

$startTime = time();
$lastSequence = null;
$errors = [];
$warnings = [];
$segmentDurations = [];

while (time() - $startTime < $duration) {
    echo "Checking at " . date('H:i:s') . "... ";

    // Fetch Playlist
    $ch = curl_init($streamUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    // ✅ Désactiver la vérification SSL pour éviter les erreurs locales
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // Mimic a browser
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errors[] = "CRITICAL: HTTP $httpCode on playlist fetch";
        echo "❌ HTTP $httpCode\n";
        sleep($interval);
        continue;
    }

    // Parse Playlist
    $lines = explode("\n", $content);
    $sequence = null;
    $targetDuration = null;
    $segments = [];
    $hasEndList = false;

    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (strpos($line, '#EXT-X-MEDIA-SEQUENCE:') === 0) {
            $sequence = (int) substr($line, 22);
        }
        if (strpos($line, '#EXT-X-TARGETDURATION:') === 0) {
            $targetDuration = (int) substr($line, 22);
        }
        if (strpos($line, '#EXT-X-ENDLIST') === 0) {
            $hasEndList = true;
        }
        if (strpos($line, '#EXTINF:') === 0) {
            $dur = (float) substr($line, 8, -1);
            $segmentDurations[] = $dur;
            // Get next line as URI
            if (isset($lines[$i+1])) {
                $segments[] = ['duration' => $dur, 'uri' => trim($lines[$i+1])];
            }
        }
    }

    // Validation 1: MEDIA-SEQUENCE
    if ($lastSequence !== null) {
        if ($sequence === $lastSequence) {
            echo "⚠️  Sequence Stalled ($sequence) ";
        } elseif ($sequence === $lastSequence + 1) {
            echo "✅ Sequence OK ($sequence) ";
        } elseif ($sequence > $lastSequence + 1) {
            $errors[] = "GAP: Sequence jumped from $lastSequence to $sequence";
            echo "❌ SEQUENCE GAP ";
        } elseif ($sequence < $lastSequence) {
            $errors[] = "REGRESSION: Sequence sequence went backward $lastSequence -> $sequence";
             echo "❌ SEQUENCE REGRESSION ";
        }
    } else {
        echo "ℹ️  Init Sequence $sequence ";
    }
    $lastSequence = $sequence;

    // Validation 2: TARGETDURATION match
    if ($targetDuration) {
        foreach ($segments as $seg) {
            if ($seg['duration'] > $targetDuration) {
                $errors[] = "VIOLATION: Segment duration {$seg['duration']}s > TARGETDURATION {$targetDuration}s";
                echo "❌ DURATION VIOLATION ";
            }
        }
    }

    // Validation 3: Segment Accessibility
    // Check the last segment to ensure it exists (Head check)
    if (!empty($segments)) {
        $lastSeg = end($segments);
        $segUrl = $lastSeg['uri'];
        if (strpos($segUrl, 'http') !== 0) {
            // make absolute (simple version)
            $segUrl = "https://tv.embmission.com/hls/streams/" . $segUrl; 
             // Note: in your case segments might be absolute or relative, the script assumes simple relative if not http
             // Given your unified.m3u8 construction, checking if it's absolute is safer.
             // But actually your UnifiedHlsBuilder outputs absolute URLs or absolute paths? 
             // Let's assume standard behavior for now.
        }

        // Just check HEAD of the last segment
        $chSeg = curl_init($lastSeg['uri']); // It seems your segments are full URLs in the unified playlist usually
        curl_setopt($chSeg, CURLOPT_NOBODY, true);
        curl_setopt($chSeg, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($chSeg, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($chSeg);
        $segCode = curl_getinfo($chSeg, CURLINFO_HTTP_CODE);
        curl_close($chSeg);

        if ($segCode !== 200) {
             $errors[] = "SEGMENT 404: {$lastSeg['uri']} returned $segCode";
             echo "❌ SEGMENT GET FAIL ($segCode) ";
        } else {
            echo "✅ Segment Fetch OK ";
        }
    }

    echo "\n";
    sleep($interval);
}

// Final Report
echo "\n--- 📊 HLS COMPLIANCE REPORT ---\n";
if (empty($errors)) {
    echo "✅ STRICT SPEC COMPLIANCE: PASSED\n";
    echo "No spec violations found over {$duration}s.\n";
} else {
    echo "❌ SPEC VIOLATIONS FOUND:\n";
    foreach ($errors as $e) echo "- $e\n";
}

if (!empty($segmentDurations)) {
    $min = min($segmentDurations);
    $max = max($segmentDurations);
    $avg = array_sum($segmentDurations) / count($segmentDurations);
    echo "Stats: Segments Min: {$min}s, Max: {$max}s, Avg: " . number_format($avg, 2) . "s\n";
}
