<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class EmbedController extends Controller
{
    public function __invoke(?string $videoId = null): View
    {
        // Support path /embed/VIDEO_ID et query /embed?id=VIDEO_ID (chaînes partenaires)
        $videoId = trim($videoId ?? '');
        if ($videoId === '') {
            $videoId = trim(request()->query('id', ''));
        }
        $origin = request()->getSchemeAndHttpHost();
        $minimal = request()->boolean('minimal');

        return view('embed', [
            'videoId' => $videoId,
            'origin' => $origin,
            'minimal' => $minimal,
        ]);
    }
}
