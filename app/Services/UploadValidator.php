<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UploadValidator
{
    /**
     * Allowed MIME types and their mapped extensions.
     */
    protected array $allowedMimes = [
        'image/jpeg' => ['jpeg', 'jpg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'video/mp4' => ['mp4'],
    ];

    /**
     * Maximum file size in bytes (10MB).
     */
    protected int $maxSize = 10485760; // 10 * 1024 * 1024

    /**
     * Minimum image dimensions.
     */
    protected int $minWidth = 800;
    protected int $minHeight = 600;

    /**
     * Validate the uploaded file against all constraints.
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file): void
    {
        // 1. Malware Scan Hook (simulated)
        if (!$this->scan($file)) {
            throw ValidationException::withMessages([
                'artwork_file' => ['Potential security threat detected: Malware scan failed.'],
            ]);
        }

        // 2. Size validation
        if ($file->getSize() > $this->maxSize) {
            throw ValidationException::withMessages([
                'artwork_file' => ['The file size exceeds the maximum limit of 10MB.'],
            ]);
        }

        // 3. MIME / Type validation
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        
        $isValidMime = array_key_exists($mime, $this->allowedMimes);
        $isValidExt = in_array($ext, ['webp', 'jpeg', 'jpg', 'png', 'pdf', 'mp4']);

        if (!$isValidMime && !$isValidExt) {
            throw ValidationException::withMessages([
                'artwork_file' => ['Unsupported file format. Allowed formats: WEBP, JPEG, PNG, PDF, MP4.'],
            ]);
        }

        // 4. Image Dimensions Verification (only for images)
        if (str_starts_with($mime, 'image/') || in_array($ext, ['webp', 'jpeg', 'jpg', 'png'])) {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
                if ($width < $this->minWidth || $height < $this->minHeight) {
                    throw ValidationException::withMessages([
                        'artwork_file' => ["Image dimensions must be at least {$this->minWidth}x{$this->minHeight} pixels. Got {$width}x{$height}."],
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'artwork_file' => ['Unable to retrieve image dimensions.'],
                ]);
            }
        }
    }

    public function scan(UploadedFile $file): bool
    {
        $filename = strtolower($file->getClientOriginalName());
        
        // EICAR standard anti-virus test file name
        if ($filename === 'eicar.txt' || $filename === 'eicar.com') {
            return false;
        }

        // Check content for standard EICAR or mock test string
        try {
            $content = $file->getContent();
            if ($content !== '' && (
                str_contains($content, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*') ||
                str_contains($content, 'MOCK-MALWARE-SIGNATURE-TEST')
            )) {
                return false;
            }
        } catch (\Throwable $e) {
            // Fallback to real path if getContent fails
            if (file_exists($file->getRealPath())) {
                $content = @file_get_contents($file->getRealPath());
                if ($content !== false && str_contains($content, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
                    return false;
                }
            }
        }

        return true;
    }
}
