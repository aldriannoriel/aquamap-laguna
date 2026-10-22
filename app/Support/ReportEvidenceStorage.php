<?php

namespace App\Support;

use App\Models\ReportReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ReportEvidenceStorage
{
    public static function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Invalid evidence size.');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $bytes = file_get_contents($file->getPathname());
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (! isset($types[$extension]) || ! is_string($bytes)) {
            throw new RuntimeException('Invalid evidence type.');
        }
        if ($extension === 'docx') {
            self::inspectWordArchive($file->getPathname());
        } elseif ($extension === 'doc' ? ! in_array($mime, ['application/msword', 'application/x-ole-storage', 'application/CDFV2'], true) : $mime !== $types[$extension]) {
            throw new RuntimeException('Evidence content and extension do not match.');
        }
        if (str_starts_with($types[$extension], 'image/')) {
            $size = getimagesizefromstring($bytes);
            if (! $size || $size[0] * $size[1] > 20000000 || ! ($image = @imagecreatefromstring($bytes))) {
                throw new RuntimeException('Invalid or excessive image dimensions.');
            }
            imagedestroy($image);
        }
        if ($extension === 'pdf' && (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -2048), '%%EOF')
            || preg_match('~/JavaScript\b|/JS\b|/Launch\b|/EmbeddedFile\b~i', $bytes))) {
            throw new RuntimeException('Invalid or active PDF content.');
        }
        if ($extension === 'doc' && (! str_starts_with($bytes, hex2bin('d0cf11e0a1b11ae1'))
            || ! str_contains($bytes, "W\0o\0r\0d\0D\0o\0c\0u\0m\0e\0n\0t\0")
            || preg_match('/VBA|AutoOpen|Ole10Native/i', str_replace("\0", '', $bytes)))) {
            throw new RuntimeException('Invalid or active Word document.');
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? 'evidence.'.$extension;
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME) ?: 'evidence', 0, 179 - strlen($extension)).'.'.$extension;

        return ['original_name' => $name, 'mime_type' => $types[$extension], 'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'extension' => $extension];
    }

    private static function inspectWordArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Invalid Word archive.');
        }
        try {
            if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                throw new RuntimeException('Word document structure is missing.');
            }
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $expanded += $entry['size'];
                if ($expanded > 25 * 1024 * 1024 || str_contains($entry['name'], '..') || str_contains($entry['name'], '\\')
                    || str_starts_with($entry['name'], '/') || preg_match('~vbaProject|embeddings/|\.(exe|dll|js|vbs|com)$~i', $entry['name'])) {
                    throw new RuntimeException('Unsafe Word archive content.');
                }
            }
            $types = $zip->getFromName('[Content_Types].xml');
            $document = $zip->getFromName('word/document.xml');
            if (! is_string($types) || ! str_contains($types, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
                || ! is_string($document) || ! preg_match('/<(?:[A-Za-z_]+:)?document\b/', $document)
                || preg_match('/<!DOCTYPE|<!ENTITY|macroEnabled/i', $types.$document)) {
                throw new RuntimeException('Invalid or active Word XML.');
            }
        } finally {
            $zip->close();
        }
    }

    public static function store(UploadedFile $file, ReportReview $review, array &$createdPaths): void
    {
        $metadata = self::inspect($file);
        $path = $review->pollution_report_id.'/'.$review->id.'/'.Str::uuid().'.'.$metadata['extension'];
        $disk = Storage::disk('report-evidence');
        if ($disk->exists($path)) {
            throw new RuntimeException('Evidence path already exists.');
        }
        $createdPaths[] = $path;
        $written = $disk->put($path, file_get_contents($file->getPathname()), ['visibility' => 'private']);
        if (! $written || ! $disk->exists($path) || $disk->size($path) !== $metadata['byte_size']
            || ! hash_equals($metadata['sha256'], hash('sha256', $disk->get($path)))) {
            throw new RuntimeException('Evidence storage could not be verified.');
        }
        unset($metadata['extension']);
        $review->evidence()->forceCreate([...$metadata, 'path' => $path]);
    }

    public static function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                if (! Storage::disk('report-evidence')->delete($path)) {
                    throw new RuntimeException('Evidence cleanup failed.');
                }
            } catch (Throwable $exception) {
                Log::error('New uncommitted evidence needs private cleanup.', ['exception_type' => $exception::class]);
            }
        }
    }
}
