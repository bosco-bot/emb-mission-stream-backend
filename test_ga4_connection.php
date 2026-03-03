<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GA4DataService;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;

echo "🔍 Testing GA4 Connectivity...\n\n";

$radioId = env('GA4_PROPERTY_ID_RADIO');
$tvId = env('GA4_PROPERTY_ID_TV');
$credsPath = env('GA4_CREDENTIALS_PATH');

echo "1. Configuration Check:\n";
echo "   - Radio Property ID: " . ($radioId ? "✅ Set ($radioId)" : "❌ Missing") . "\n";
echo "   - TV Property ID:    " . ($tvId ? "✅ Set ($tvId)" : "❌ Missing") . "\n";
echo "   - Credentials Path:  " . ($credsPath ? "✅ Set ($credsPath)" : "❌ Missing") . "\n";

if ($credsPath && file_exists($credsPath)) {
    echo "   - Credentials File:  ✅ Found\n";
} else {
    echo "   - Credentials File:  ❌ NOT FOUND at $credsPath\n";
    exit(1);
}

echo "\n2. Connectivity Test (Real-time Active Users):\n";

function testProperty($propertyId, $name) {
    if (!$propertyId) {
        echo "   - $name: Skipped (No ID)\n";
        return;
    }

    try {
        $service = new GA4DataService($propertyId);
        // Force client init to check creds immediately
        $users = $service->getActiveUsersRealTime();
        echo "   - $name (ID: $propertyId): ✅ OK - Active Users: $users\n";
    } catch (\Exception $e) {
        echo "   - $name (ID: $propertyId): ❌ ERROR - " . $e->getMessage() . "\n";
    }
}

testProperty($radioId, "Web Radio");
testProperty($tvId, "Web TV");

echo "\nDone.\n";
