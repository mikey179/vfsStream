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
use function fclose;
use function fgets;
use function file_get_contents;
use function fopen;
use function fread;
use function fwrite;
use function strlen;
use function uniqid;

/**
 * Test for using more than one stream pointing at the same virtual file
 */
class SimultaneousStreamsTestCase extends BaseFunctionalTestCase
{
    /**
     * @test
     */
    public function multipleReadsOnSameFileHaveDifferentPointers(): void
    {
        $content = uniqid();
        $this->setMockFileContent($content);

        $fp1 = fopen($this->getMockFileName(), 'rb');
        $fp2 = fopen($this->getMockFileName(), 'rb');

        assertThat(fread($fp1, 4096), equals($content));
        assertThat(fread($fp2, 4096), equals($content));

        fclose($fp1);
        fclose($fp2);
    }

    /**
     * @test
     */
    public function canReadTwoStreamsAlternately(): void
    {
        $this->setMockFileContent("Line 1\nLine 2\n");

        $stream1 = fopen($this->getMockFileName(), 'rb');
        $stream2 = fopen($this->getMockFileName(), 'rb');

        assertThat(fgets($stream1), equals("Line 1\n"), 'First line from first stream');
        assertThat(fgets($stream2), equals("Line 1\n"), 'First line from second stream');

        assertThat(fgets($stream1), equals("Line 2\n"), 'Second line from first stream');
        assertThat(fgets($stream2), equals("Line 2\n"), 'Second line from second stream');
    }

    /**
     * @test
     */
    public function canReadTwoStreamsSequentially(): void
    {
        $this->setMockFileContent("Line 1\nLine 2\n");

        $stream1 = fopen($this->getMockFileName(), 'rb');
        $stream2 = fopen($this->getMockFileName(), 'rb');

        assertThat(fgets($stream1), equals("Line 1\n"), 'First line from first stream');
        assertThat(fgets($stream1), equals("Line 2\n"), 'Second line from first stream');

        assertThat(fgets($stream2), equals("Line 1\n"), 'First line from second stream');
        assertThat(fgets($stream2), equals("Line 2\n"), 'Second line from second stream');
    }

    /**
     * @test
     */
    public function multipleWritesOnSameFileHaveDifferentPointers(): void
    {
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        $url = $this->getMockFileName();

        $fp1 = fopen($url, 'wb');
        $fp2 = fopen($url, 'wb');

        fwrite($fp1, $contentA . $contentA);
        fwrite($fp2, $contentB);

        fclose($fp1);
        fclose($fp2);

        assertThat(file_get_contents($url), equals($contentB . $contentA));
    }

    /**
     * @test
     */
    public function readsAndWritesOnSameFileHaveDifferentPointers(): void
    {
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        $url = $this->getMockFileName();

        $fp1 = fopen($url, 'wb');
        $fp2 = fopen($url, 'rb');

        fwrite($fp1, $contentA);
        $contentBeforeWrite = fread($fp2, strlen($contentA));

        fwrite($fp1, $contentB);
        $contentAfterWrite = fread($fp2, strlen($contentB));

        fclose($fp1);
        fclose($fp2);

        assertThat($contentBeforeWrite, equals($contentA));
        assertThat($contentAfterWrite, equals($contentB));
    }

    /**
     * @test
     */
    public function canReadLinesWrittenBeforeReading(): void
    {
        $this->setMockFileContent("Line 1\nLine 2\n");

        $writeStream = fopen($this->getMockFileName(), 'wb');
        $readStream = fopen($this->getMockFileName(), 'rb');

        fwrite($writeStream, "Line 1\n");
        fwrite($writeStream, "Line 2\n");

        assertThat(fgets($readStream), equals("Line 1\n"), 'Should be able to read first line');
        assertThat(fgets($readStream), equals("Line 2\n"), 'Should be able to read second line');
    }

    /**
     * @test
     */
    public function canReadLinesWrittenAfterFirstRead(): void
    {
        $this->setMockFileContent("Line 1\nLine 2\n");

        $writeStream = fopen($this->getMockFileName(), 'wb');
        $readStream = fopen($this->getMockFileName(), 'rb');

        fwrite($writeStream, "Line 1\n");
        fwrite($writeStream, "Line 2\n");
        assertThat(fgets($readStream), equals("Line 1\n"), 'Read first line written before first read');

        fwrite($writeStream, "Line 3\n");
        assertThat(fgets($readStream), equals("Line 2\n"), 'Read second line written before first read');
        assertThat(fgets($readStream), equals("Line 3\n"), 'Read line written after first read');
    }
}
