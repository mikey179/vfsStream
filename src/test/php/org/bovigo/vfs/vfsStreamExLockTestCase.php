<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs;

use PHPUnit\Framework\TestCase;
use const LOCK_EX;
use const LOCK_UN;
use function bovigo\assert\assertThat;
use function bovigo\assert\expect;
use function bovigo\assert\predicate\equals;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function fwrite;

/**
 * Test for LOCK_EX behaviour related to file_put_contents().
 *
 * @group   lock_fpc
 */
class vfsStreamExLockTestCase extends TestCase
{
    /**
     * set up test environment
     */
    protected function setUp(): void
    {
        $root = vfsStream::setup();
        vfsStream::newFile('testfile')->at($root);
    }

    /**
     * This test verifies the current behaviour where vfsStream URLs do not work
     * with file_put_contents() and LOCK_EX. The test is intended to break once
     * PHP changes this so we get notified about the change.
     *
     * @test
     */
    public function filePutContentsWithLockShouldReportError(): void
    {
        expect(static function (): void {
            file_put_contents(vfsStream::url('root/testfile'), "some string\n", LOCK_EX);
        })->triggers()
          ->withMessage('file_put_contents(): Exclusive locks may only be set for regular files');
    }

    /**
     * @test
     */
    public function flockShouldPass(): void
    {
        $fp = fopen(vfsStream::url('root/testfile'), 'w');
        flock($fp, LOCK_EX);
        fwrite($fp, "another string\n");
        flock($fp, LOCK_UN);
        fclose($fp);
        assertThat(
            file_get_contents(vfsStream::url('root/testfile')),
            equals("another string\n")
        );
    }
}
