<?php

namespace App\Services;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ServerDependencyService
{
    /**
     * @return array<int, array{name: string, type: string, purpose: string, required: bool, installed: bool, executable: ?string, version: string}>
     */
    public function statuses(): array
    {
        return [
            $this->phpExtension('Fileinfo', 'fileinfo', 'File type and upload detection'),
            $this->phpExtension('Imagick PHP extension', 'imagick', 'Image variants and PDF/image processing'),
            $this->phpExtension('Mbstring', 'mbstring', 'Unicode text handling'),
            $this->phpExtension('PHP ZIP extension', 'zip', 'ZIP creation and extraction in PHP'),

            $this->command('Composer', [['composer', '--version', '--no-ansi']], 'PHP dependency installation', true),
            $this->command('Node.js', [['node', '--version']], 'Frontend asset builds and deployment', true),
            $this->command('npm', [['npm', '--version']], 'Frontend dependency installation and builds', true),
            $this->command('Git', [['git', '--version']], 'Website deployment and revision information', true),

            $this->imageMagick(),
            $this->command('Ghostscript', [['gs', '--version']], 'PDF rendering used by ImageMagick', false),
            $this->command('FFmpeg', [['ffmpeg', '-version']], 'Video thumbnail generation', false),
            $this->command('FFprobe', [['ffprobe', '-version']], 'Video metadata and duration detection', false),
            $this->command('Poppler PDF text', [['pdftotext', '-v']], 'Searchable text extraction from expense PDFs', false),
            $this->command('ZIP command', [['zip', '-v']], 'File, media, and finance backup archives', false),
            $this->command('Unzip command', [['unzip', '-v']], 'ZIP archive inspection and extraction', false),
            $this->command('Database dump client', [
                ['mysqldump', '--version'],
                ['mariadb-dump', '--version'],
            ], 'MySQL/MariaDB database backups', false),
            $this->command('Database client', [
                ['mysql', '--version'],
                ['mariadb', '--version'],
            ], 'MySQL/MariaDB database restores', false),
            $this->command('gzip', [['gzip', '--version']], 'Database backup compression and restore', false),
        ];
    }

    /**
     * @return array{name: string, type: string, purpose: string, required: bool, installed: bool, executable: ?string, version: string}
     */
    private function phpExtension(string $name, string $extension, string $purpose): array
    {
        $installed = extension_loaded($extension);
        $version = $installed ? phpversion($extension) : false;

        return [
            'name' => $name,
            'type' => 'PHP extension',
            'purpose' => $purpose,
            'required' => true,
            'installed' => $installed,
            'executable' => $extension,
            'version' => $installed ? ($version !== false ? $version : 'Bundled with PHP') : 'Not installed',
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $alternatives
     * @return array{name: string, type: string, purpose: string, required: bool, installed: bool, executable: ?string, version: string}
     */
    private function command(string $name, array $alternatives, string $purpose, bool $required): array
    {
        $finder = new ExecutableFinder;

        foreach ($alternatives as $command) {
            $executable = $finder->find($command[0]);
            if ($executable === null) {
                continue;
            }

            $command[0] = $executable;

            return [
                'name' => $name,
                'type' => 'Command-line tool',
                'purpose' => $purpose,
                'required' => $required,
                'installed' => true,
                'executable' => basename($executable),
                'version' => $this->versionOutput($command),
            ];
        }

        return [
            'name' => $name,
            'type' => 'Command-line tool',
            'purpose' => $purpose,
            'required' => $required,
            'installed' => false,
            'executable' => null,
            'version' => 'Not installed',
        ];
    }

    /**
     * @return array{name: string, type: string, purpose: string, required: bool, installed: bool, executable: ?string, version: string}
     */
    private function imageMagick(): array
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $details = \Imagick::getVersion();
                $version = trim((string) ($details['versionString'] ?? ''));

                return [
                    'name' => 'ImageMagick',
                    'type' => 'System library',
                    'purpose' => 'Image variants, PDF previews, and PDF/image attachments',
                    'required' => false,
                    'installed' => true,
                    'executable' => 'via Imagick',
                    'version' => $version !== '' ? $version : 'Installed',
                ];
            } catch (Throwable) {
                // Fall through to checking the command-line installation.
            }
        }

        $status = $this->command('ImageMagick', [
            ['magick', '-version'],
            ['convert', '-version'],
        ], 'Image variants, PDF previews, and PDF/image attachments', false);
        $status['type'] = 'System library';

        return $status;
    }

    /** @param array<int, string> $command */
    private function versionOutput(array $command): string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(5);
            $process->run();

            $output = trim($process->getOutput());
            if ($output === '') {
                $output = trim($process->getErrorOutput());
            }

            $firstLine = strtok(str_replace("\r", '', $output), "\n");

            return is_string($firstLine) && trim($firstLine) !== ''
                ? trim($firstLine)
                : 'Installed (version unavailable)';
        } catch (Throwable) {
            return 'Installed (version unavailable)';
        }
    }
}
