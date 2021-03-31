<?php

declare(strict_types=1);

namespace bovigo\vfs\tests;

use bovigo\vfs\vfsStream;
use bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Base class for setting up and configuring fixtures for stream wrapper functional tests.
 * See README.md for an explanation of the two implementations of each method
 */
abstract class BaseFunctionalTestCase extends TestCase
{
    /** @var vfsStreamFile */
    protected $vfsFile;

    /** @var string */
    private $realFileName;

    /**
     * Create real or virtual files for use by all tests
     */
    protected function setUp(): void
    {
        if (isset($_ENV['TEST_THE_TESTS_WITH_REAL_FILES'])) {
            $this->realFileName = tempnam(sys_get_temp_dir(), 'vfsstream_test_');
        } else {
            $root = vfsStream::setup();
            $this->vfsFile = vfsStream::newFile('test');
            $this->vfsFile->at($root);
        }
    }

    protected function getMockFileName(): string
    {
        if (isset($_ENV['TEST_THE_TESTS_WITH_REAL_FILES'])) {
            return $this->realFileName;
        } else {
            return $this->vfsFile->url();
        }
    }

    protected function setMockFileContent(string $content): void
    {
        if (isset($_ENV['TEST_THE_TESTS_WITH_REAL_FILES'])) {
             file_put_contents($this->realFileName, $content);
        } else {
            $this->vfsFile->setContent($content);
        }
    }

    /**
     * The virtual file system will clean itself up, but real files need real deletion
     */
    protected function tearDown(): void
    {
        if (isset($_ENV['TEST_THE_TESTS_WITH_REAL_FILES'])) {
            unlink($this->realFileName);
        }
    }
}
