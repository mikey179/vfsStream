<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs\tests;

use bovigo\vfs\vfsStream;

use function bovigo\assert\assertEmptyString;
use function bovigo\assert\assertFalse;
use function bovigo\assert\assertThat;
use function bovigo\assert\assertTrue;
use function bovigo\assert\predicate\equals;
use function fclose;
use function feof;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function is_file;
use function rename;
use function unlink;

use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

/**
 * Test for bovigo\vfs\vfsStreamWrapper.
 */
class vfsStreamWrapperFileTestCase extends vfsStreamWrapperBaseTestCase
{
    /**
     * @test
     */
    public function file_get_contentsReturnsFileContents(): void
    {
        assertThat(file_get_contents($this->fileInRoot->url()), equals('file 2'));
    }

    /**
     * @test
     */
    public function file_get_contentsReturnsFalseForDirectories(): void
    {
        assertFalse(@file_get_contents($this->subdir->url()));
    }

    /**
     * @test
     * @group  permissions
     * @group  bug_15
     */
    public function file_get_contentsReturnsEmptyStringForNonReadableFile(): void
    {
        vfsStream::newFile('new.txt', 0000)->at($this->root)->withContent('content');
        assertEmptyString(@file_get_contents(vfsStream::url('root/new.txt')));
    }

    /**
     * @test
     */
    public function file_put_contentsReturnsAmountOfWrittenBytes(): void
    {
        assertThat(
            file_put_contents($this->fileInRoot->url(), 'baz is not bar'),
            equals(14)
        );
    }

    /**
     * @test
     */
    public function file_put_contentsExistingFile(): void
    {
        file_put_contents($this->fileInRoot->url(), 'baz is not bar');
        assertThat($this->fileInRoot->getContent(), equals('baz is not bar'));
    }

    /**
     * @test
     * @group  permissions
     * @group  bug_15
     */
    public function file_put_contentsExistingFileNonWritableDirectory(): void
    {
        $this->root->chmod(0000);
        file_put_contents($this->fileInRoot->url(), 'This does work.');
        assertThat($this->fileInRoot->getContent(), equals('This does work.'));
    }

    /**
     * @test
     * @group  permissions
     * @group  bug_15
     */
    public function file_put_contentsExistingNonWritableFile(): void
    {
        $this->fileInRoot->chmod(0400);
        assertFalse(@file_put_contents($this->fileInRoot->url(), 'This does not work.'));
        assertThat($this->fileInRoot->getContent(), equals('file 2'));
    }

    /**
     * assert that file_put_contents() delivers correct file contents
     *
     * @test
     */
    public function file_put_contentsNonExistingFile(): void
    {
        file_put_contents($this->root->url() . '/baznot.bar', 'baz is not bar');
        assertThat($this->root->getChild('baznot.bar')->getContent(), equals('baz is not bar'));
    }

    /**
     * @test
     * @group  permissions
     * @group  bug_15
     */
    public function file_put_contentsNonExistingFileNonWritableDirectory(): void
    {
        $this->root->chmod(0000);
        assertFalse(@file_put_contents(vfsStream::url('root/new.txt'), 'This does not work.'));
    }

    /**
     * @test
     */
    public function filePointerKnowsPositionInFile(): void
    {
        $fp = fopen($this->fileInSubdir->url(), 'r');
        assertThat(ftell($fp), equals(0));
        fclose($fp);
    }

    /**
     * @return int[][]
     */
    public function seekArgs(): array
    {
        return [
            [2, SEEK_SET, 2],
            [1, SEEK_CUR, 1],
            [1, SEEK_END, 7],
        ];
    }

    /**
     * @test
     * @dataProvider  seekArgs
     */
    public function canSeekInFile(int $where, int $whence, int $pos): void
    {
        $fp = fopen($this->fileInSubdir->url(), 'r');
        assertThat(fseek($fp, $where, $whence), equals(0));
        assertThat(ftell($fp), equals($pos));
        fclose($fp);
    }

    /**
     * @test
     */
    public function recognizesEof(): void
    {
        $fp = fopen($this->fileInSubdir->url(), 'r');
        fseek($fp, 1, SEEK_END);
        fread($fp, 1);
        assertTrue(feof($fp));
        fclose($fp);
    }

    /**
     * @test
     */
    public function readsFromSeekedPosition(): void
    {
        $fp = fopen($this->fileInSubdir->url(), 'r');
        fseek($fp, 2);
        assertThat(fread($fp, 1), equals('l'));
        fclose($fp);
    }

    /**
     * @test
     */
    public function readingMovesPosition(): void
    {
        $fp = fopen($this->fileInSubdir->url(), 'r');
        fread($fp, 8092);
        assertThat(ftell($fp), equals(6));
        fclose($fp);
    }

    /**
     * @test
     */
    public function is_fileReturnsFalseForDirectory(): void
    {
        assertFalse(is_file($this->root->url()));
    }

    /**
     * @test
     */
    public function is_fileReturnsTrueForFile(): void
    {
        assertTrue(is_file($this->fileInSubdir->url()));
    }

    /**
     * @test
     */
    public function is_fileReturnsFalseForNonExisting(): void
    {
        assertFalse(is_file($this->root->url() . '/doesNotExist'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function issue13CanNotOverwriteFiles(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        file_put_contents($vfsFile, 'd');
        assertThat(file_get_contents($vfsFile), equals('d'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function appendContentIfOpenedWithModeA(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'ab');
        fwrite($fp, 'd');
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('testd'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canOverwriteNonExistingFileWithModeX(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        $fp = fopen($vfsFile, 'xb');
        fwrite($fp, 'test');
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('test'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotOverwriteExistingFileWithModeX(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        assertFalse(@fopen($vfsFile, 'xb'));
        assertThat(file_get_contents($vfsFile), equals('test'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotOpenNonExistingFileReadonly(): void
    {
        assertFalse(@fopen(vfsStream::url('root/doesNotExist.txt'), 'rb'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotOpenNonExistingFileReadAndWrite(): void
    {
        assertFalse(@fopen(vfsStream::url('root/doesNotExist.txt'), 'rb+'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotOpenWithIllegalMode(): void
    {
        assertFalse(@fopen($this->fileInRoot->url(), 'invalid'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotWriteToReadOnlyFile(): void
    {
        $fp = fopen($this->fileInRoot->url(), 'rb');
        assertThat(fread($fp, 4096), equals('file 2'));
        assertThat(fwrite($fp, 'foo'), equals(0));
        fclose($fp);
        assertThat($this->fileInRoot->getContent(), equals('file 2'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotReadFromWriteOnlyFileWithModeW(): void
    {
        $fp = fopen($this->fileInRoot->url(), 'wb');
        assertEmptyString(fread($fp, 4096));
        assertThat(fwrite($fp, 'foo'), equals(3));
        fseek($fp, 0);
        assertEmptyString(fread($fp, 4096));
        fclose($fp);
        assertThat($this->fileInRoot->getContent(), equals('foo'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotReadFromWriteOnlyFileWithModeA(): void
    {
        $fp = fopen($this->fileInRoot->url(), 'ab');
        assertEmptyString(fread($fp, 4096));
        assertThat(fwrite($fp, 'foo'), equals(3));
        fseek($fp, 0);
        assertEmptyString(fread($fp, 4096));
        fclose($fp);
        assertThat($this->fileInRoot->getContent(), equals('file 2foo'));
    }

    /**
     * @test
     * @group  issue7
     * @group  issue13
     */
    public function canNotReadFromWriteOnlyFileWithModeX(): void
    {
        $vfsFile = vfsStream::url('root/modeXtest.txt');
        $fp = fopen($vfsFile, 'xb');
        assertEmptyString(fread($fp, 4096));
        assertThat(fwrite($fp, 'foo'), equals(3));
        fseek($fp, 0);
        assertEmptyString(fread($fp, 4096));
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('foo'));
    }

    /**
     * @test
     * @group  permissions
     * @group  bug_15
     */
    public function canNotRemoveFileFromDirectoryWithoutWritePermissions(): void
    {
        $this->root->chmod(0000);
        assertFalse(@unlink($this->fileInRoot->url()));
        assertTrue($this->root->hasChild('file2'));
    }

    /**
     * @test
     * @group  issue_30
     */
    public function truncatesFileWhenOpenedWithModeW(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'wb');
        assertEmptyString(file_get_contents($vfsFile));
        fclose($fp);
    }

    /**
     * @test
     * @group  issue_30
     */
    public function createsNonExistingFileWhenOpenedWithModeC(): void
    {
        $vfsFile = vfsStream::url('root/tobecreated.txt');
        $fp = fopen($vfsFile, 'cb');
        fwrite($fp, 'some content');
        assertTrue($this->root->hasChild('tobecreated.txt'));
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('some content'));
    }

    /**
     * @test
     * @group  issue_30
     */
    public function createsNonExistingFileWhenOpenedWithModeCplus(): void
    {
        $vfsFile = vfsStream::url('root/tobecreated.txt');
        $fp = fopen($vfsFile, 'cb+');
        fwrite($fp, 'some content');
        assertTrue($this->root->hasChild('tobecreated.txt'));
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('some content'));
    }

    /**
     * @test
     * @group  issue_30
     */
    public function doesNotTruncateFileWhenOpenedWithModeC(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'cb');
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('test'));
    }

    /**
     * @test
     * @group  issue_30
     */
    public function setsPointerToStartWhenOpenedWithModeC(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'cb');
        assertThat(ftell($fp), equals(0));
        fclose($fp);
    }

    /**
     * @test
     * @group  issue_30
     */
    public function doesNotTruncateFileWhenOpenedWithModeCplus(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'cb+');
        fclose($fp);
        assertThat(file_get_contents($vfsFile), equals('test'));
    }

    /**
     * @test
     * @group  issue_30
     */
    public function setsPointerToStartWhenOpenedWithModeCplus(): void
    {
        $vfsFile = vfsStream::url('root/overwrite.txt');
        file_put_contents($vfsFile, 'test');
        $fp = fopen($vfsFile, 'cb+');
        assertThat(ftell($fp), equals(0));
        fclose($fp);
    }

    /**
     * @test
     */
    public function cannotOpenExistingNonwritableFileWithModeA(): void
    {
        $this->fileInSubdir->chmod(0400);
        assertFalse(@fopen($this->fileInSubdir->url(), 'a'));
    }

    /**
     * @test
     */
    public function cannotOpenExistingNonwritableFileWithModeW(): void
    {
        $this->fileInSubdir->chmod(0400);
        assertFalse(@fopen($this->fileInSubdir->url(), 'w'));
    }

    /**
     * @test
     */
    public function cannotOpenNonReadableFileWithModeR(): void
    {
        $this->fileInSubdir->chmod(0000);
        assertFalse(@fopen($this->fileInSubdir->url(), 'r'));
    }

    /**
     * @test
     */
    public function cannotRenameToNonWritableDir(): void
    {
        $this->subdir->chmod(0000);
        assertFalse(@rename($this->fileInRoot->url(), vfsStream::url('root/bar/baz3')));
    }

    /**
     * @test
     * @group permissions
     * @group issue_38
     */
    // public function cannotReadFileFromNonReadableDir()
    // {
    //     $this->markTestSkipped('Ignored for now, see https://github.com/mikey179/vfsStream/issues/38');
    //     $this->subdir->chmod(0000);
    //     assertFalse(@file_get_contents($this->fileInSubdir->url()));
    // }
}
