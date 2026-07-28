<?php

namespace HorizonsPlus\CollectorLaravel\Services;

use Illuminate\Support\Facades\Process;

class HealthCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $composer = $this->readComposerJson();
        $dependencies = $composer['require'] ?? [];

        return [
            'status' => 'ok',
            'collected_at' => now()->toIso8601String(),
            'runtime' => [
                'php' => PHP_VERSION,
            ],
            'project' => [
                'name' => $composer['name'] ?? config('app.name', 'laravel-app'),
                'version' => $composer['version'] ?? '0.0.0',
            ],
            'framework' => $this->detectFramework(),
            'dependencies' => $dependencies,
            'audit' => $this->runComposerAudit(),
            'updates' => $this->runComposerOutdated(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(): array
    {
        $path = base_path('composer.json');

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{name: string, version: string}|null
     */
    private function detectFramework(): ?array
    {
        if (class_exists(\Illuminate\Foundation\Application::class)) {
            return [
                'name' => 'laravel',
                'version' => app()->version(),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function runComposerAudit(): array
    {
        $result = $this->runComposer(['audit', '--format=json']);
        $output = trim($result->output());
        $parsed = $output !== '' ? json_decode($output, true) : null;

        if (! is_array($parsed)) {
            return [
                'tool' => 'composer',
                'error' => $result->errorOutput() ?: 'composer audit failed',
                'metadata' => null,
                'vulnerabilities' => [],
            ];
        }

        $vulnerabilities = [];

        foreach ($parsed['advisories'] ?? [] as $packageName => $advisories) {
            foreach ($advisories as $advisory) {
                $vulnerabilities[] = [
                    'name' => $packageName,
                    'severity' => strtolower($advisory['severity'] ?? 'unknown'),
                    'title' => $advisory['title'] ?? $advisory['cve'] ?? 'Vulnérabilité détectée',
                    'range' => $advisory['affectedVersions'] ?? null,
                    'fix_available' => ! empty($advisory['reportedAt']),
                    'via_version' => null,
                ];
            }
        }

        return [
            'tool' => 'composer',
            'error' => null,
            'metadata' => [
                'abandoned' => $parsed['abandoned'] ?? [],
            ],
            'vulnerabilities' => $vulnerabilities,
        ];
    }

    /**
     * Packages directs avec une version plus récente disponible.
     *
     * @return array{tool: string, error: string|null, packages: list<array<string, mixed>>}
     */
    private function runComposerOutdated(): array
    {
        $result = $this->runComposer(['outdated', '--direct', '--format=json']);
        $output = trim($result->output());

        // composer outdated renvoie parfois du JSON sur stdout même avec exit code != 0
        $parsed = $output !== '' ? json_decode($output, true) : null;

        if (! is_array($parsed)) {
            $stderr = trim($result->errorOutput());
            $stderrParsed = $stderr !== '' ? json_decode($stderr, true) : null;
            $parsed = is_array($stderrParsed) ? $stderrParsed : null;
        }

        if (! is_array($parsed)) {
            return [
                'tool' => 'composer',
                'error' => $result->errorOutput() ?: ($result->successful() ? null : 'composer outdated failed'),
                'packages' => [],
            ];
        }

        $packages = [];

        foreach ($parsed['installed'] ?? [] as $pkg) {
            $name = $pkg['name'] ?? null;
            $current = $pkg['version'] ?? null;
            $latest = $pkg['latest'] ?? null;
            $status = $pkg['latest-status'] ?? null;

            if (! $name || ! $current || ! $latest || $current === $latest) {
                continue;
            }

            // Ignorer les paquets déjà à jour
            if ($status === 'up-to-date') {
                continue;
            }

            $packages[] = [
                'name' => $name,
                'current' => ltrim((string) $current, 'v'),
                'wanted' => ltrim((string) ($pkg['latest'] ?? $latest), 'v'),
                'latest' => ltrim((string) $latest, 'v'),
                'latest_status' => $status,
                'severity' => $this->severityForComposerStatus($status, (string) $current, (string) $latest),
            ];
        }

        return [
            'tool' => 'composer',
            'error' => null,
            'packages' => $packages,
        ];
    }

    private function severityForComposerStatus(?string $status, string $current, string $latest): string
    {
        // update-possible = major (semver breaking) ; semver-safe-update = minor/patch
        if ($status === 'update-possible') {
            return 'moderate';
        }

        if ($status === 'semver-safe-update') {
            return $this->isMajorBump($current, $latest) ? 'moderate' : 'low';
        }

        return $this->isMajorBump($current, $latest) ? 'moderate' : 'low';
    }

    private function isMajorBump(string $current, string $latest): bool
    {
        $currentMajor = (int) explode('.', ltrim($current, 'v'))[0];
        $latestMajor = (int) explode('.', ltrim($latest, 'v'))[0];

        return $latestMajor > $currentMajor;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runComposer(array $arguments): \Illuminate\Contracts\Process\ProcessResult
    {
        $composer = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'composer.bat' : 'composer';

        return Process::path(base_path())
            ->timeout(120)
            ->run(array_merge([$composer], $arguments));
    }
}
