<?php

namespace App\Tests\Controller;

use App\Controller\Trait\LocalRedirectTrait;
use PHPUnit\Framework\TestCase;

final class LocalRedirectTraitTest extends TestCase
{
    private function localPath(?string $path): string
    {
        return (new class {
            use LocalRedirectTrait {
                localPath as public;
            }
        })->localPath($path);
    }

    /** @dataProvider accepted */
    public function testKeepsSameSitePaths(string $path): void
    {
        $this->assertSame($path, $this->localPath($path));
    }

    /** @dataProvider rejected */
    public function testRejectsAnythingThatCanLeaveTheSite(?string $path): void
    {
        $this->assertSame('', $this->localPath($path));
    }

    /** @return iterable<string, array{string}> */
    public static function accepted(): iterable
    {
        yield 'root' => ['/'];
        yield 'path' => ['/task'];
        yield 'query' => ['/project/3?tab=files&x=1'];
    }

    /** @return iterable<string, array{?string}> */
    public static function rejected(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'protocol relative' => ['//evil.example'];
        yield 'backslash host' => ['/\\evil.example'];
        yield 'absolute url' => ['https://evil.example'];
        yield 'no slash' => ['task'];
        yield 'newline' => ["/task\r\nLocation: https://evil.example"];
    }
}
