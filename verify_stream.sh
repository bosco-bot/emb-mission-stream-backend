#!/bin/bash
echo "🔍 Checking HLS Stream Stability & Compliance..."
echo "Target: https://tv.embmission.com/hls/streams/unified.m3u8"
echo "Duration: 60 seconds"
echo "---------------------------------------------------"
php /var/www/emb-mission/validate_hls_compliance.php
