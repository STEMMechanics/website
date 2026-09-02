<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractor
{
    public function isAvailable(): bool
    {
        return (new ExecutableFinder)->find('pdftotext') !== null;
    }

    public function extract(?string $storagePath): ?string
    {
        $storagePath = trim((string) $storagePath);
        if ($storagePath === ''
            || strtolower((string) pathinfo($storagePath, PATHINFO_EXTENSION)) !== 'pdf'
            || ! Storage::disk('local')->exists($storagePath)) {
            return null;
        }

        $binary = (new ExecutableFinder)->find('pdftotext');
        if ($binary === null) {
            return null;
        }

        try {
            $process = new Process([
                $binary,
                '-layout',
                '-nopgbrk',
                Storage::disk('local')->path($storagePath),
                '-',
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $text = Str::of($process->getOutput())
                ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '')
                ->replaceMatches('/[ \t]+/', ' ')
                ->replaceMatches('/\R{3,}/u', "\n\n")
                ->trim()
                ->toString();

            return $text !== '' ? $text : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
