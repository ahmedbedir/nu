<?php

namespace App\Http\Controllers\Api;

use App\Models\BookingEvent;
use App\Models\SosIncident;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated operations stream.
 *
 * This is a conservative SSE adapter for local/staging. Production should put Reverb
 * or an equivalent authenticated broker in front of the same event/outbox facts.
 * It never broadcasts PHI, exact addresses or live provider coordinates.
 */
class OperationsController extends Controller
{
    /** GET /api/v1/admin/operations/stream */
    public function stream(): StreamedResponse
    {
        return response()->stream(function () {
            $started = now();
            $lastEventId = 0;
            $lastSosId = 0;

            // Bounded stream prevents a PHP worker from being held indefinitely in local mode.
            while ($started->diffInSeconds(now()) < 20) {
                $events = BookingEvent::query()
                    ->where('id', '>', $lastEventId)
                    ->orderBy('id')
                    ->limit(50)
                    ->get();

                foreach ($events as $event) {
                    $lastEventId = $event->id;
                    echo 'id: '.$event->id."\n";
                    echo "event: booking\n";
                    echo 'data: '.json_encode([
                        'booking_id' => $event->booking_id,
                        'event' => $event->event,
                        'from_state' => $event->from_state,
                        'to_state' => $event->to_state,
                        'created_at' => $event->created_at?->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE)."\n\n";
                }

                $sos = SosIncident::query()
                    ->where('id', '>', $lastSosId)
                    ->where('created_at', '>=', $started)
                    ->orderBy('id')
                    ->get();

                foreach ($sos as $incident) {
                    $lastSosId = $incident->id;
                    echo 'id: sos-'.$incident->id."\n";
                    echo "event: sos\n";
                    echo 'data: '.json_encode([
                        'sos_id' => $incident->public_id,
                        'state' => $incident->state,
                        'reporter_role' => $incident->reporter_role,
                        'occurred_at' => $incident->occurred_at?->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE)."\n\n";
                }

                echo ": heartbeat\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                usleep(500000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}