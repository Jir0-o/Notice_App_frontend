<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppUpdateController extends Controller
{
    public function index()
    {
        $updates = AppUpdate::query()
            ->orderBy('platform')
            ->get();

        return view('admin.app_updates.index', compact('updates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            'latest_version' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d+(\.\d+){0,3}$/',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
            'published_at' => [
                'nullable',
                'date',
            ],
        ], [
            'platform.regex' => 'Platform may contain only letters, numbers, dash and underscore. Example: android, ios.',
            'latest_version.regex' => 'Version format is invalid. Example: 1.2.1',
        ]);

        $platform = strtolower(trim($data['platform']));

        $appUpdate = AppUpdate::updateOrCreate(
            [
                'platform' => $platform,
            ],
            [
                'latest_version' => trim($data['latest_version']),
                'is_active' => (bool) $data['is_active'],
                'published_at' => $data['published_at'] ?? now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'App update version saved successfully.',
            'data' => [
                'id' => $appUpdate->id,
                'platform' => $appUpdate->platform,
                'latest_version' => $appUpdate->latest_version,
                'is_active' => (bool) $appUpdate->is_active,
                'published_at' => optional($appUpdate->published_at)->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function destroy(AppUpdate $appUpdate)
    {
        $appUpdate->delete();

        return response()->json([
            'success' => true,
            'message' => 'App update version removed successfully.',
        ]);
    }
}