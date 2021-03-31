<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs\tests;

use function bovigo\assert\assertThat;
use function bovigo\assert\predicate\equals;
use function fgetc;
use function fgets;
use function fopen;
use function fread;
use function ftell;
use function ftruncate;

/**
 * Test for behaviour of feof(), which should match that of real files
 */
class FtellTestCase extends BaseFunctionalTestCase
{
    /**
     * @test
     */
    public function emptyFileStartsAtZero(): void
    {
        $this->setMockFileContent('');
        $stream = fopen($this->getMockFileName(), 'rb');
        assertThat(ftell($stream), equals(0));
    }

    /**
     * @test
     */
    public function nonEmptyFileStartsAtZero(): void
    {
        $this->setMockFileContent('abcdef');
        $stream = fopen($this->getMockFileName(), 'rb');
        assertThat(ftell($stream), equals(0));
    }

    /**
     * @test
     */
    public function fileStartsAtZeroIfOpenedWithTruncation(): void
    {
        $this->setMockFileContent('abcdef');
        $stream = fopen($this->getMockFileName(), 'wb');
        assertThat(ftell($stream), equals(0));
    }

    public function filePointsAtZeroAfterTruncation(): void
    {
        $this->setMockFileContent('abcdef');
        $stream = fopen($this->getMockFileName(), 'rb+');
        ftruncate($stream, 0);
        assertThat(ftell($stream), equals(0));
    }

    public function filePointsAtEndAfterPartialTruncation(): void
    {
        $this->setMockFileContent('abc');
        $stream = fopen($this->getMockFileName(), 'ab+');
        ftruncate($stream, 3);
        assertThat(ftell($stream), equals(3));
    }

    public function readSingleByteProgressesPointer(): void
    {
        $this->setMockFileContent('abc');
        $stream = fopen($this->getMockFileName(), 'rb');
        fgetc($stream);
        assertThat(ftell($stream), equals(1));
    }

    public function readSingleByteTwiceProgressesPointerTwice(): void
    {
        $this->setMockFileContent('abc');
        $stream = fopen($this->getMockFileName(), 'rb');
        fgetc($stream);
        fgetc($stream);
        assertThat(ftell($stream), equals(2));
    }

    public function readAllBytesDoesntPointPastEndOfFile(): void
    {
        $this->setMockFileContent('abc');
        $stream = fopen($this->getMockFileName(), 'rb');
        fgetc($stream);
        fgetc($stream);
        fgetc($stream);
        fgetc($stream);
        assertThat(ftell($stream), equals(3));
    }

    public function readSeveralBytesProgressesPointer(): void
    {
        $this->setMockFileContent('abcdef');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 3);
        assertThat(ftell($stream), equals(3));
    }

    /**
     * @test
     */
    public function readMoreThanSizeDoesntPointPastEndOfFile(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 10);
        assertThat(ftell($stream), equals(9));
    }

    public function readLineProgressesPointer(): void
    {
        $this->setMockFileContent("123\n456");
        $stream = fopen($this->getMockFileName(), 'rb');
        fgets($stream);
        assertThat(ftell($stream), equals(3));
    }
}
