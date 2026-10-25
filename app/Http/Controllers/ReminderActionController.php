<?php

namespace App\Http\Controllers;

use App\Models\NotificationTarget;
use App\Services\Notifications\NotificationActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReminderActionController extends Controller
{
    public function __invoke(Request $request, NotificationTarget $target, NotificationActions $actions): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:ack,snooze'],
            'minutes' => ['required_if:action,snooze', 'integer'],
        ]);

        if ($data['action'] === 'ack') {
            $actions->acknowledge($target, $target->caretaker);
        } else {
            try {
                $actions->snooze($target, (int) $data['minutes']);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
