<?php

namespace Tests\Unit;

use App\Support\AppRevision;
use PHPUnit\Framework\TestCase;

class AppRevisionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir().'/app-revision-'.bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0777, true);
        AppRevision::flush();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
        AppRevision::flush();

        parent::tearDown();
    }

    public function test_reads_branch_and_short_hash_from_loose_ref(): void
    {
        $gitDir = $this->tmpDir.'/.git';
        mkdir($gitDir.'/refs/heads', 0777, true);
        file_put_contents($gitDir.'/HEAD', "ref: refs/heads/main\n");
        file_put_contents($gitDir.'/refs/heads/main', "c05e3619f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5\n");

        $this->assertSame([
            'branch' => 'main',
            'hash' => 'c05e361',
        ], AppRevision::fromGitDirectory($gitDir));
    }

    public function test_reads_hash_from_packed_refs(): void
    {
        $gitDir = $this->tmpDir.'/.git';
        mkdir($gitDir, 0777, true);
        file_put_contents($gitDir.'/HEAD', "ref: refs/heads/release/1.0\n");
        file_put_contents($gitDir.'/packed-refs', <<<'REFS'
# pack-refs with: peeled fully-peeled sorted
abc1234def5678901234567890abcdef12345678 refs/heads/release/1.0

REFS);

        $this->assertSame([
            'branch' => 'release/1.0',
            'hash' => 'abc1234',
        ], AppRevision::fromGitDirectory($gitDir));
    }

    public function test_reads_detached_head(): void
    {
        $gitDir = $this->tmpDir.'/.git';
        mkdir($gitDir, 0777, true);
        file_put_contents($gitDir.'/HEAD', "9f8e7d6c5b4a3210fedcba9876543210abcdef12\n");

        $this->assertSame([
            'branch' => 'HEAD',
            'hash' => '9f8e7d6',
        ], AppRevision::fromGitDirectory($gitDir));
    }

    public function test_follows_gitdir_file_for_worktrees(): void
    {
        $realGit = $this->tmpDir.'/real-git';
        mkdir($realGit.'/refs/heads', 0777, true);
        file_put_contents($realGit.'/HEAD', "ref: refs/heads/dev\n");
        file_put_contents($realGit.'/refs/heads/dev', "1234567890abcdef1234567890abcdef12345678\n");

        $gitFile = $this->tmpDir.'/.git';
        file_put_contents($gitFile, 'gitdir: '.$realGit."\n");

        $this->assertSame([
            'branch' => 'dev',
            'hash' => '1234567',
        ], AppRevision::fromGitDirectory($gitFile));
    }

    public function test_returns_nulls_when_git_is_missing(): void
    {
        $this->assertSame([
            'branch' => null,
            'hash' => null,
        ], AppRevision::fromGitDirectory($this->tmpDir.'/missing'));
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        $items = scandir($path) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removeDirectory($path.DIRECTORY_SEPARATOR.$item);
        }

        rmdir($path);
    }
}
