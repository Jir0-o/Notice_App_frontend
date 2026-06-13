<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use Illuminate\Http\Request;

class AppUpdateCheckController extends Controller
{
    public function check(Request $request)
    {
        $data = $request->validate([
            'platform' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            'current_version' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d+(\.\d+){0,3}$/',
            ],
        ], [
            'platform.regex' => 'Platform may contain only letters, numbers, dash and underscore.',
            'current_version.regex' => 'Version format is invalid. Example: 1.2.1',
        ]);

        $platform = strtolower(trim($data['platform']));
        $currentVersion = trim($data['current_version']);

        $appUpdate = AppUpdate::query()
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        if (!$appUpdate) {
            return response()->json([
                'success' => true,
                'message' => 'No active update version is published for this platform.',
                'data' => [
                    'platform' => $platform,
                    'current_version' => $currentVersion,
                    'latest_version' => null,
                    'is_matched' => true,
                    'update_available' => false,
                    'status' => 'not_configured',
                    'published_at' => null,
                ],
            ]);
        }

        $latestVersion = $appUpdate->latest_version;
        $compare = version_compare($currentVersion, $latestVersion);

        if ($compare === 0) {
            $status = 'matched';
            $isMatched = true;
            $updateAvailable = false;
            $message = 'You are using the latest app version.';
        } elseif ($compare < 0) {
            $status = 'update_available';
            $isMatched = false;
            $updateAvailable = true;
            $message = 'A new app version is available. Please update your app to version ' . $latestVersion . '.';
        } else {
            $status = 'newer_than_published';
            $isMatched = false;
            $updateAvailable = false;
            $message = 'Your app version is newer than the published version.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'platform' => $appUpdate->platform,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'is_matched' => $isMatched,
                'update_available' => $updateAvailable,
                'status' => $status,
                'published_at' => optional($appUpdate->published_at)->toISOString(),
            ],
        ]);
    }
}