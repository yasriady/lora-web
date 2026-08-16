<?php

namespace App\Support;

class AppRevision
{
    /**
     * @var array{branch: ?string, hash: ?string}|null
     */
    private static ?array $resolved = null;

    public static function version(): string
    {
        $version = config('app.version');

        return is_string($version) && $version !== '' ? $version : '1.0';
    }

    public static function branch(): ?string
    {
        return self::resolve()['branch'];
    }

    public static function hash(): ?string
    {
        return self::resolve()['hash'];
    }

    public static function describe(): ?string
    {
        $branch = self::branch();
        $hash = self::hash();

        if ($branch !== null && $hash !== null) {
            return $branch.' @ '.$hash;
        }

        return $branch ?? $hash;
    }

    /**
     * @return array{branch: ?string, hash: ?string}
     */
    public static function resolve(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $branch = self::nonEmpty(config('app.git_branch'));
        $hash = self::shortHash(config('app.git_hash'));

        if ($branch === null || $hash === null) {
            $fromGit = self::fromGitDirectory(base_path('.git'));
            $branch ??= $fromGit['branch'];
            $hash ??= $fromGit['hash'];
        }

        return self::$resolved = [
            'branch' => $branch,
            'hash' => $hash,
        ];
    }

    public static function flush(): void
    {
        self::$resolved = null;
    }

    /**
     * @return array{branch: ?string, hash: ?string}
     */
    public static function fromGitDirectory(string $gitPath): array
    {
        $gitPath = self::resolveGitDir($gitPath);

        if ($gitPath === null) {
            return ['branch' => null, 'hash' => null];
        }

        $head = self::readTrimmed($gitPath.'/HEAD');

        if ($head === null) {
            return ['branch' => null, 'hash' => null];
        }

        if (preg_match('#^ref:\s+refs/heads/(.+)$#', $head, $matches) === 1) {
            $branch = $matches[1];

            return [
                'branch' => $branch,
                'hash' => self::hashForRef($gitPath, 'refs/heads/'.$branch),
            ];
        }

        if (preg_match('/^[0-9a-f]{7,40}$/i', $head) === 1) {
            return [
                'branch' => 'HEAD',
                'hash' => self::shortHash($head),
            ];
        }

        return ['branch' => null, 'hash' => null];
    }

    private static function resolveGitDir(string $gitPath): ?string
    {
        if (is_dir($gitPath)) {
            return $gitPath;
        }

        if (! is_file($gitPath)) {
            return null;
        }

        $contents = self::readTrimmed($gitPath);

        if ($contents === null || preg_match('/^gitdir:\s*(.+)$/i', $contents, $matches) !== 1) {
            return null;
        }

        $gitDir = $matches[1];

        if (! str_starts_with($gitDir, '/')) {
            $gitDir = dirname($gitPath).DIRECTORY_SEPARATOR.$gitDir;
        }

        $real = realpath($gitDir);

        return $real !== false && is_dir($real) ? $real : null;
    }

    private static function hashForRef(string $gitPath, string $ref): ?string
    {
        $loose = self::readTrimmed($gitPath.'/'.$ref);

        if ($loose !== null) {
            return self::shortHash($loose);
        }

        $packed = self::readTrimmed($gitPath.'/packed-refs');

        if ($packed === null) {
            return null;
        }

        foreach (preg_split('/\R/', $packed) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            if (preg_match('/^([0-9a-f]{7,40})\s+'.preg_quote($ref, '/').'$/i', $line, $matches) === 1) {
                return self::shortHash($matches[1]);
            }
        }

        return null;
    }

    private static function shortHash(mixed $value): ?string
    {
        $hash = self::nonEmpty($value);

        if ($hash === null || preg_match('/^[0-9a-f]{7,40}$/i', $hash) !== 1) {
            return null;
        }

        return strtolower(substr($hash, 0, 7));
    }

    private static function nonEmpty(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private static function readTrimmed(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $contents = trim($contents);

        return $contents !== '' ? $contents : null;
    }
}
