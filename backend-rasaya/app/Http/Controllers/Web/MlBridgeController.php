<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\MlClient;
use Illuminate\Http\Request;

class MlBridgeController extends Controller
{
    public function health(MlClient $ml)
    {
        return response()->json($ml->health());
    }

    // contoh: kirim dummy ke ML untuk tes
    public function tryAnalyze(MlClient $ml)
    {
        $items = [
            ['id' => 'ref1', 'text' => 'hari ini capek sekali, kami diberikan banyak PR sama pak alfons, semoga bisa', 'lang_hint' => 'id'],
            ['id' => 'guru1', 'text' => 'juan telat setiap hari dalam minggu ini, setiap istirahat juga selalu telat masuk kelas', 'lang_hint' => 'id-kupang'],
            ['id' => 'ref2', 'text' => 'today was great, I felt motivated to finish my homework', 'lang_hint' => 'en'],
        ];

        $out = $ml->analyze($items);
        return response()->json($out);
    }
}
