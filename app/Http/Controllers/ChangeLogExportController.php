<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ChangeLog\ChangeLogAccessCheck;
use App\Support\ChangeLog\ChangeLogFilter;
use App\Support\ChangeLog\ChangeLogReader;
use Illuminate\Http\Request;
use Mortel\Support\Actor;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of exactly what the change log shows for a filter: the same query, the same hidden parts, every page, then the
 * archive. Semicolons and a byte order mark, so a Dutch spreadsheet opens it as is. Reads only.
 */
final class ChangeLogExportController extends Controller
{
    public function __invoke(Request $request, ChangeLogReader $reader): StreamedResponse
    {
        $user = $request->user();

        if ($user === null || ! ChangeLogAccessCheck::allows($user)) {
            abort(403);
        }

        $filter = ChangeLogFilter::fromInput($request->query('who'), $request->query('part'), $request->query('from'), $request->query('to'));
        $viewer = Actor::user($user);
        $levels = $reader->levels($user);
        $timezone = config()->string('app.timezone');

        return response()->streamDownload(function () use ($reader, $viewer, $levels, $filter, $timezone): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', 'Tijd', 'Wie', 'Soort', 'Wat', 'Toelichting', 'Onderdeel', 'Ongedaan gemaakt', 'Vóór de overstap'], ';', '"', '');

            foreach ($reader->all($viewer, $levels, $filter) as $row) {
                $at = $row->at->setTimezone($timezone);

                fputcsv($out, array_map(self::cell(...), [
                    $at->format('Y-m-d'),
                    $at->format('H:i'),
                    $row->actor->full(),
                    $row->actor->kind->label(),
                    $row->sentence,
                    $row->detail ?? '',
                    $row->part->label,
                    $row->undoneBy !== null ? 'ja' : 'nee',
                    $row->archived ? 'ja' : 'nee',
                ]), ';', '"', '');
            }

            fclose($out);
        }, 'Wijzigingslog '.now($timezone)->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A cell that a spreadsheet would read as a formula gets a leading quote (CSV injection). */
    private static function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
