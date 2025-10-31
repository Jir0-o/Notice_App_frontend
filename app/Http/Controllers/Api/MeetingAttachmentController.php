<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingDetail;
use App\Models\MeetingAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;

class MeetingAttachmentController extends Controller
{
    /**
     * GET /api/meeting-details/{id}/attachments
     */
    public function index($id): JsonResponse
    {
        $detail = MeetingDetail::find($id);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting detail not found.',
            ], 404);
        }

        $attachments = MeetingAttachment::where('meeting_detail_id', $detail->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($att) {
                $publicPath = public_path($att->file_path);
                return [
                    'id'          => $att->id,
                    'file_name'   => $att->file_name,
                    'file_type'   => $att->file_type,
                    'file_path'   => $att->file_path, // relative: meeting_attachments/...
                    'uploaded_at' => $att->uploaded_at?->toDateTimeString(),
                    'exists'      => file_exists($publicPath),
                    'url'         => file_exists($publicPath) ? url($att->file_path) : null,
                ];
            });

        return response()->json([
            'success'     => true,
            'meeting_id'  => $detail->meeting_id,
            'detail_id'   => $detail->id,
            'attachments' => $attachments,
        ]);
    }

    /**
     * GET /api/meeting-attachments/{id}/download
     */
    public function downloadOne($id)
    {
        $attachment = MeetingAttachment::find($id);
        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ], 404);
        }

        $fullPath = public_path($attachment->file_path);
        if (!file_exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on disk.',
            ], 404);
        }

        return response()->download($fullPath, $attachment->file_name);
    }

    /**
     * GET /api/meeting-details/{id}/attachments/download-all
     */
    public function downloadAll($id)
    {
        $detail = MeetingDetail::find($id);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting detail not found.',
            ], 404);
        }

        $attachments = MeetingAttachment::where('meeting_detail_id', $detail->id)->get();
        if ($attachments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No attachments for this meeting detail.',
            ], 404);
        }

        // temp zip in storage/app/tmp
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipName = 'meeting-'.$detail->id.'-attachments.zip';
        $zipPath = $tmpDir.'/'.$zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to create zip.',
            ], 500);
        }

        foreach ($attachments as $att) {
            $fullPath = public_path($att->file_path);
            if (!file_exists($fullPath)) {
                continue;
            }

            $nameInZip = $att->file_name ?: basename($att->file_path);
            if ($zip->locateName($nameInZip) !== false) {
                $nameInZip = Str::random(4).'_'.$nameInZip;
            }

            $zip->addFile($fullPath, $nameInZip);
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }
}