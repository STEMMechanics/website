<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class NativeBrowserDialogUsageTest extends TestCase
{
    private const BANNED_DIALOG_PATTERN = '/window\.(?:confirm|alert)\s*\(|(?<![\w$.])(?:confirm|alert)\s*\((?!\)\s*\{)/';

    public function test_frontend_source_does_not_use_native_browser_dialogs(): void
    {
        $violations = [];

        foreach ($this->frontendSourceFiles() as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents, sprintf('Could not read %s', $path));

            if (! preg_match_all(self::BANNED_DIALOG_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $violations[] = sprintf(
                    '%s:%d (%s)',
                    $this->relativePath($path),
                    $this->lineNumber($contents, $offset),
                    trim($match)
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Replace native browser dialogs with SM.confirm/SM.notice.\n".implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function frontendSourceFiles(): array
    {
        $paths = [];

        foreach ([
            $this->projectRoot().'/public',
            $this->projectRoot().'/resources',
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $relativePath = $this->relativePath($path);

                if ($this->shouldSkip($relativePath) || ! $this->isFrontendSourceFile($relativePath)) {
                    continue;
                }

                $paths[] = $path;
            }
        }

        sort($paths);

        return $paths;
    }

    private function isFrontendSourceFile(string $relativePath): bool
    {
        return str_ends_with($relativePath, '.blade.php')
            || preg_match('/\.(?:js|jsx|ts|tsx|vue)$/', $relativePath) === 1;
    }

    private function shouldSkip(string $relativePath): bool
    {
        return str_starts_with($relativePath, 'public/build/')
            || str_starts_with($relativePath, 'public/thumbnails/');
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->projectRoot(), '', $path), '/');
    }

    private function lineNumber(string $contents, int $offset): int
    {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }
}
