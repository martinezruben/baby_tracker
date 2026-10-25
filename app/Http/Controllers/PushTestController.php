<?php

namespace App\Http\Controllers;

use App\Services\Notifications\Channels\WebPushChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PushTestController extends Controller
{
    public function __invoke(Request $request, WebPushChannel $channel): JsonResponse
    {
        try {
            $channel->sendTest($request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => trans('ui.push.test_failed')], 422);
        }

        return response()->json(['message' => trans('ui.push.test_sent')]);
    }
}
