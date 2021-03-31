<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs\tests;

use const SEEK_END;
use const SEEK_SET;
use function bovigo\assert\assertFalse;
use function bovigo\assert\assertThat;
use function bovigo\assert\assertTrue;
use function bovigo\assert\predicate\equals;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function fread;
use function fseek;
use function ftruncate;
use function rewind;

/**
 * Test for behaviour of feof(), which should match that of real files
 */
class FeofTestCase extends BaseFunctionalTestCase
{
    /**
     * @test
     */
    public function feofIsFalseWhenFileOpened(): void
    {
        $this->setMockFileContent("Line 1\n");

        $stream = fopen($this->getMockFileName(), 'rb');

        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseWhenEmptyFileOpened(): void
    {
        $this->setMockFileContent('');

        $stream = fopen($this->getMockFileName(), 'rb');

        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsTrueAfterEmptyFileRead(): void
    {
        $this->setMockFileContent('');

        $stream = fopen($this->getMockFileName(), 'rb');

        fgets($stream);

        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseWhenStreamRewound(): void
    {
        $this->setMockFileContent("Line 1\n");

        $stream = fopen($this->getMockFileName(), 'rb');

        fgets($stream);
        rewind($stream);
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseWhenSeekingToStart(): void
    {
        $fp = fopen($this->getMockFileName(), 'rb');
        fseek($fp, 0, SEEK_SET);
        assertFalse(feof($fp));
        fclose($fp);
    }

    /**
     * @test
     */
    public function feofIsFalseWhenSeekingToEnd(): void
    {
        $fp = fopen($this->getMockFileName(), 'rb');
        fseek($fp, 0, SEEK_END);
        assertFalse(feof($fp));
        fclose($fp);
    }

    /**
     * @test
     */
    public function feofIsFalseWhenSeekingPastEnd(): void
    {
        $fp = fopen($this->getMockFileName(), 'rb');
        fseek($fp, 10, SEEK_END);
        assertFalse(feof($fp));
        fclose($fp);
    }

    /**
     * @test
     */
    public function feofIsFalseWhenEmptyStreamRewound(): void
    {
        $this->setMockFileContent('');

        $stream = fopen($this->getMockFileName(), 'rb');

        fgets($stream);
        rewind($stream);
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseAfterReadingLastLine(): void
    {
        $this->setMockFileContent("Line 1\n");

        $stream = fopen($this->getMockFileName(), 'rb');

        assertThat(fgets($stream), equals("Line 1\n"));
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsTrueAfterReadingBeyondLastLine(): void
    {
        $this->setMockFileContent("Line 1\n");

        $stream = fopen($this->getMockFileName(), 'rb');

        fgets($stream);
        fgets($stream);

        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function readLessThanSizeDoesNotReachEof(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 3);
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function readSizeDoesNotReachEof(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 9);
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function readSizeReachesEofOnNextRead(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 9);
        fread($stream, 1);
        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function readMoreThanSizeReachesEof(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 10);
        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function readMoreThanSizeReachesEofOnNextRead(): void
    {
        $this->setMockFileContent('123456789');
        $stream = fopen($this->getMockFileName(), 'rb');
        fread($stream, 10);
        fread($stream, 1);
        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function streamIsNotEofAfterTruncate(): void
    {
        $this->setMockFileContent('test');
        $stream = fopen($this->getMockFileName(), 'rb+');
        fread($stream, 4);
        ftruncate($stream, 0);
        assertFalse(feof($stream));
    }
}
