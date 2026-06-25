<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceSelfieService
{
    private const DISK = 'local';

    /**
     * @var array<int, string>
     */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function hasSelfie(mixed $selfie): bool
    {
        if ($selfie instanceof UploadedFile) {
            return true;
        }

        return is_string($selfie) && trim($selfie) !== '';
    }

    public function storeForAttendance(
        Employee $employee,
        AttendanceLog $attendanceLog,
        mixed $selfie,
        mixed $capturedAt = null,
        ?array $deviceInfo = null,
        ?array $metadata = null,
    ): AttendanceSelfie {
        [$contents, $mimeType, $resolvedMetadata] = $this->normalizeToJpeg($selfie);
        $capturedAtAt = $this->resolveCapturedAt($capturedAt);
        $path = $this->buildPath(
            $attendanceLog->company_id ?: $employee->getEffectiveCompanyId(),
            (int) $employee->getKey(),
            $capturedAtAt,
        );

        Storage::disk(self::DISK)->put($path, $contents);

        $attendanceSelfie = AttendanceSelfie::query()->updateOrCreate(
            [
                'attendance_log_id' => $attendanceLog->getKey(),
            ],
            [
                'company_id' => $attendanceLog->company_id ?: $employee->getEffectiveCompanyId(),
                'employee_id' => (int) $employee->getKey(),
                'image_path' => $path,
                'captured_at' => $capturedAtAt,
                'device_info' => $deviceInfo,
                'metadata' => array_filter([
                    'source_mime_type' => $mimeType,
                    ...($metadata ?? []),
                    ...$resolvedMetadata,
                ], static fn (mixed $value): bool => $value !== null),
            ],
        );

        if ($attendanceLog->selfie_path !== $path) {
            $attendanceLog->forceFill([
                'selfie_path' => $path,
            ])->save();
        }

        return $attendanceSelfie;
    }

    public static function disk(): string
    {
        return self::DISK;
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function normalizeToJpeg(mixed $selfie): array
    {
        if ($selfie instanceof UploadedFile) {
            return $this->normalizeUploadedFile($selfie);
        }

        if (is_string($selfie) && trim($selfie) !== '') {
            return $this->normalizeDataUrl($selfie);
        }

        throw ValidationException::withMessages([
            'selfie' => 'A valid selfie image is required.',
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function normalizeUploadedFile(UploadedFile $file): array
    {
        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));

        $this->assertSupportedMimeType($mimeType);

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'selfie' => 'The uploaded selfie image could not be read.',
            ]);
        }

        return [
            $this->convertToJpeg($contents, $mimeType),
            $mimeType,
            [
                'original_filename' => $file->getClientOriginalName(),
                'original_extension' => $file->getClientOriginalExtension(),
                'original_size_bytes' => $file->getSize(),
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function normalizeDataUrl(string $value): array
    {
        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', trim($value), $matches)) {
            throw ValidationException::withMessages([
                'selfie' => 'The selfie upload payload is invalid.',
            ]);
        }

        $mimeType = strtolower($matches[1]);

        $this->assertSupportedMimeType($mimeType);

        $contents = base64_decode($matches[2], true);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'selfie' => 'The selfie image data could not be decoded.',
            ]);
        }

        return [
            $this->convertToJpeg($contents, $mimeType),
            $mimeType,
            [
                'upload_transport' => 'data_url',
                'original_size_bytes' => strlen($contents),
            ],
        ];
    }

    private function assertSupportedMimeType(string $mimeType): void
    {
        if (! in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'selfie' => 'Selfie images must be JPEG, PNG, or WEBP.',
            ]);
        }
    }

    private function convertToJpeg(string $contents, string $mimeType): string
    {
        if (in_array($mimeType, ['image/jpeg', 'image/jpg'], true)) {
            return $contents;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages([
                'selfie' => 'Selfie conversion to JPEG is not available on this server.',
            ]);
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw ValidationException::withMessages([
                'selfie' => 'The selfie image file could not be processed.',
            ]);
        }

        ob_start();
        imagejpeg($image, null, 90);
        $jpegContents = ob_get_clean();
        imagedestroy($image);

        if (! is_string($jpegContents) || $jpegContents === '') {
            throw ValidationException::withMessages([
                'selfie' => 'The selfie image could not be converted to JPEG.',
            ]);
        }

        return $jpegContents;
    }

    private function resolveCapturedAt(mixed $value): Carbon
    {
        $timezone = config('app.timezone');

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        if (filled($value)) {
            return Carbon::parse((string) $value, $timezone);
        }

        return now($timezone);
    }

    private function buildPath(?int $companyId, int $employeeId, Carbon $capturedAt): string
    {
        return sprintf(
            'attendance-selfies/%d/%d/%s/%s/%s.jpg',
            $companyId ?: 0,
            $employeeId,
            $capturedAt->format('Y'),
            $capturedAt->format('m'),
            (string) Str::uuid(),
        );
    }
}
