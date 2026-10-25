<?php

namespace App\Http\Controllers;

use App\Support\ActiveBaby;
use App\Support\FamilyClock;
use App\Support\ReportData;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $baby = ActiveBaby::for($request);
        $days = (int) $request->query('dias');
        $days = in_array($days, ReportData::RANGES, true) ? $days : 7;

        return Inertia::render('Reportes', [
            'baby' => $baby->only(['id', 'first_name']),
            'ranges' => ReportData::RANGES,
            'report' => (new ReportData(FamilyClock::for($request->user()->family)))->build($baby, $days),
        ]);
    }

    /** Todos los registros del bebé activo en CSV (se abre en Excel), del más reciente al más antiguo. */
    public function export(Request $request, Timeline $timeline): StreamedResponse
    {
        $baby = ActiveBaby::for($request);
        $clock = FamilyClock::for($request->user()->family);
        $name = 'kidu-'.str($baby->first_name)->slug().'-'.$clock->now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($timeline, $baby, $clock) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel reconoce UTF-8
            fputcsv($out, [trans('ui.reportes.csv.when'), trans('ui.reportes.csv.type'), trans('ui.reportes.csv.by'), trans('ui.reportes.csv.details')]);

            $cursor = null;
            do {
                $page = $timeline->page($baby, null, $cursor);
                foreach ($page['entries'] as $entry) {
                    fputcsv($out, [
                        $clock->toLocal(CarbonImmutable::parse($entry['at'])),
                        trans('ui.registrar.tiles.'.$entry['type']),
                        $entry['by'],
                        $this->details($entry['data']),
                    ]);
                }
                $cursor = $page['next'];
            } while ($cursor);

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita que Excel ejecute como fórmula un texto escrito por una persona (=, +, -, @). */
    private function safe(string $cell): string
    {
        return preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }

    /** "clave: valor; clave: valor" sin los vacíos. */
    private function details(array $data): string
    {
        return collect($data)
            ->reject(fn ($v) => $v === null || $v === '' || $v === false)
            ->map(fn ($v, $k) => $k.': '.(is_bool($v) ? trans('ui.reportes.csv.yes') : $v))
            ->implode('; ');
    }
}
