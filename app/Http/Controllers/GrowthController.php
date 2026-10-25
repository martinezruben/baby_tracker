<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\Growth\GrowthReport;
use App\Support\Growth\GrowthStandards;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $settings = $request->user()->family->settings;
        $measure = in_array($request->query('medida'), GrowthStandards::MEASURES, true) ? $request->query('medida') : 'weight';

        $report = new GrowthReport($settings->growth_chart_standard ?? 'who', $settings->weight_unit, $settings->height_unit);

        return Inertia::render('Crecimiento', [
            'baby' => $baby->only(['id', 'first_name']),
            'measures' => GrowthStandards::MEASURES,
            'report' => $report->build($baby, $measure),
        ]);
    }
}
