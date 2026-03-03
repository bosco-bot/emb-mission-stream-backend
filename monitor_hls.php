<?php

$url = 'https://tv.embmission.com/hls/streams/unified.m3u8';
$duration = 600; // 10 minutes
$interval = 2; // 2 seconds

$lastMediaSequence = -1;
$lastProgramDateTime = null;
$startTime = time();

echo "Starting monitoring of $url for $duration seconds...\n";

while (time() - $startTime < $duration) {
    echo "Checking at " . date('H:i:s') . "...\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Mimic player
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Origin: https://hlsplayer.net']); 
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "❌ HTTP Error: $httpCode\n";
        continue;
    }

    $lines = explode("\n", $response);
    $mediaSequence = -1;
    $segments = [];
    $programDateTimes = [];
    $endlist = false;

    foreach ($lines as $line) {
        if (strpos($line, '#EXT-X-MEDIA-SEQUENCE:') === 0) {
            $mediaSequence = (int) substr($line, 22);
        }
        if (strpos($line, '#EXT-X-PROGRAM-DATE-TIME:') === 0) {
            $programDateTimes[] = substr($line, 25);
        }
        if (strpos($line, '#EXTINF:') === 0) {
            // duration
        }
        if (strpos($line, 'http') === 0 || strpos($line, '/') === 0) {
            $segments[] = $line;
        }
        if (strpos($line, '#EXT-X-ENDLIST') !== false) {
            $endlist = true;
        }
    }

    // Checks
    if ($endlist) {
        echo "❌ FAILURE: EXT-X-ENDLIST detected! Stream terminated.\n";
    }

    if ($mediaSequence !== -1) {
        if ($lastMediaSequence !== -1) {
            if ($mediaSequence < $lastMediaSequence) {
                echo "❌ FAILURE: MEDIA-SEQUENCE regression! $lastMediaSequence -> $mediaSequence\n";
            } elseif ($mediaSequence > $lastMediaSequence + 1) {
                echo "⚠️ WARNING: MEDIA-SEQUENCE gap! $lastMediaSequence -> $mediaSequence (User might skip content)\n";
            } elseif ($mediaSequence === $lastMediaSequence) {
                echo "ℹ️ INFO: Playlist not updated yet.\n";
            } else {
                echo "✅ SEQUENCE OK: $lastMediaSequence -> $mediaSequence\n";
            }
        } else {
             echo "ℹ️ INFO: Initial Sequence $mediaSequence\n";
        }
        $lastMediaSequence = $mediaSequence;
    }

    // Check Segment Gaps/Duplication visually
    echo "   Segments count: " . count($segments) . "\n";
    if (!empty($segments)) {
        echo "   First Segment: " . basename($segments[0]) . "\n";
        echo "   Last Segment: " . basename(end($segments)) . "\n";
    }
    
    if (!empty($programDateTimes)) {
         // rough check of continuity could be done here, but text output is fine for now
         echo "   Latest PDT: " . end($programDateTimes) . "\n";
    }

    sleep($interval);
}

echo "Monitoring finished.\n";
