<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSelfie;
use App\Services\Attendance\AttendanceSelfieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttendanceSelfieController extends Controller
{
    public function show(Request $request, AttendanceSelfie $attendanceSelfie): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(
            Gate::forUser($request->user())->allows('view', $attendanceSelfie->attendanceLog()->firstOrFail()),
            403,
        );

        abort_unless(
            Storage::disk(AttendanceSelfieService::disk())->exists($attendanceSelfie->image_path),
            404,
        );

        return response()->file(
            Storage::disk(AttendanceSelfieService::disk())->path($attendanceSelfie->image_path),
            [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }
}
