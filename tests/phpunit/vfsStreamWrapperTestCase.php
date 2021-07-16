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
use bovigo\vfs\vfsStreamWrapper;

use function basename;
use function bovigo\assert\assertEmptyString;
use function bovigo\assert\assertFalse;
use function bovigo\assert\assertNull;
use function bovigo\assert\assertThat;
use function bovigo\assert\assertTrue;
use function bovigo\assert\expect;
use function bovigo\assert\predicate\equals;
use function bovigo\assert\predicate\isExistingDirectory;
use function bovigo\assert\predicate\isExistingFile;
use function bovigo\assert\predicate\isNonExistingDirectory;
use function bovigo\assert\predicate\isNonExistingFile;
use function bovigo\assert\predicate\isNotEqualTo;
use function bovigo\assert\predicate\isSameAs;
use function chgrp;
use function chmod;
use function chown;
use function copy;
use function decoct;
use function dirname;
use function fclose;
use function file_exists;
use function file_get_contents;
use function filegroup;
use function filemtime;
use function fileowner;
use function fileperms;
use function filesize;
use function fopen;
use function fread;
use function fstat;
use function ftruncate;
use function fwrite;
use function is_executable;
use function is_readable;
use function is_writable;
use function rename;
use function spl_object_id;
use function stat;
use function stripos;
use function time;
use function touch;
use function uniqid;
use function unlink;

use const E_USER_WARNING;
use const E_WARNING;
use const PHP_OS;
use const PHP_VERSION_ID;

/**
 * Test for bovigo\vfs\vfsStreamWrapper.
 */
class vfsStreamWrapperTestCase extends vfsStreamWrapperBaseTestCase
{
    /**
     * ensure that a call to vfsStreamWrapper::register() resets the stream
     *
     * Implemented after a request by David Zülke.
     *
     * @test
     */
    public function resetByRegister(): void
    {
        vfsStream::setup();
        vfsStreamWrapper::register();
        assertNull(vfsStreamWrapper::getRoot());
    }

    /**
     * @test
     */
    public function setRootReturnsRoot(): void
    {
        vfsStreamWrapper::register();
        $root = vfsStream::newDirectory('root');
        assertThat(vfsStreamWrapper::setRoot($root), isSameAs($root));
    }

    /**
     * @test
     */
    public function filesizeOfDirectoryIsZero(): void
    {
        assertThat(filesize($this->root->url()), equals(0));
    }

    /**
     * @test
     */
    public function filesizeOfFile(): void
    {
        assertThat(filesize($this->fileInSubdir->url()), equals(6));
    }

    /**
     * @test
     * @dataProvider  elements
     */
    public function file_existsReturnsTrueForAllExistingFilesAndDirectories(string $element): void
    {
        assertTrue(file_exists($this->$element->url()));
    }

    /**
     * @test
     */
    public function file_existsReturnsFalseForNonExistingFiles(): void
    {
        assertFalse(file_exists($this->root->url() . '/doesNotExist'));
    }

    /**
     * @test
     */
    public function filemtime(): void
    {
        $this->root->lastModified(100)
            ->lastAccessed(100)
            ->lastAttributeModified(100);
        assertThat(filemtime($this->root->url()), equals(100));
        assertThat(filemtime($this->root->url() . '/.'), equals(100));
    }

    /**
     * @test
     * @group  issue_23
     */
    public function unlinkRemovesFiles(): void
    {
        assertTrue(unlink($this->fileInRoot->url()));
        assertFalse(file_exists($this->fileInRoot->url())); // make sure statcache was cleared
        assertFalse($this->root->hasChild('file2'));
    }

    /**
     * @test
     * @group  issue_49
     */
    public function unlinkReturnsFalseWhenFileDoesNotExist(): void
    {
        assertFalse(@unlink(vfsStream::url('root.blubb2')));
    }

    /**
     * @test
     * @group  issue_49
     */
    public function unlinkReturnsFalseWhenFileDoesNotExistAndFileWithSameNameExistsInRoot(): void
    {
        vfsStream::setup()->addChild(vfsStream::newFile('foo.blubb'));
        assertFalse(@unlink(vfsStream::url('foo.blubb')));
    }

    /**
     * @test
     */
    public function dirnameReturnsDirectoryPath(): void
    {
        assertThat(
            dirname($this->fileInSubdir->url()),
            equals($this->subdir->url())
        );
    }

    /**
     * this seems not to be fixable because dirname() does not call the stream wrapper
     *
     * @test
     */
    public function dirnameForNonExistingPathDoesNotWork(): void
    {
        assertThat(
            dirname(vfsStream::url('doesNotExist')),
            equals('vfs:') // should be '.'
        );
    }

    /**
     * @return string[][]
     */
    public function basenames(): array
    {
        return [
            [vfsStream::url('root/subdir'), 'subdir'],
            [vfsStream::url('root/subdir/file1'), 'file1'],
            [vfsStream::url('doesNotExist'), 'doesNotExist'],
        ];
    }

    /**
     * @test
     * @dataProvider  basenames
     */
    public function basename(string $path, string $basename): void
    {
        assertThat(basename($path), equals($basename));
    }

    /**
     * @test
     * @dataProvider  elements
     */
    public function is_readable(string $element): void
    {
        assertTrue(is_readable($this->$element->url()));
        assertTrue(is_readable($this->$element->url() . '/.'));
    }

    /**
     * @test
     * @dataProvider  elements
     */
    public function isNotReadableWithoutReadPermissions(string $element): void
    {
        $this->$element->chmod(0222);
        assertFalse(is_readable($this->$element->url()));
    }

    /**
     * @test
     */
    public function nonExistingIsNotReadable(): void
    {
        assertFalse(is_readable(vfsStream::url('doesNotExist')));
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsNotReadable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0400);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_readable($this->fileInRoot->url());

        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows does not honor the group/other perms
            assertTrue($actual);
        } else {
            assertFalse($actual);
        }
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsReadable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0404);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_readable($this->fileInRoot->url());

        assertTrue($actual);
    }

    /**
     * @test
     * @dataProvider  elements
     */
    public function is_writable(string $element): void
    {
        assertTrue(is_writable($this->$element->url()));
        assertTrue(is_writable($this->$element->url() . '/.'));
    }

    /**
     * @test
     * @dataProvider  elements
     */
    public function isNotWritableWithoutWritePermissions(string $element): void
    {
        $this->$element->chmod(0444);
        assertFalse(is_writable($this->$element->url()));
    }

    /**
     * @test
     */
    public function nonExistingIsNotWritable(): void
    {
        assertFalse(is_writable(vfsStream::url('doesNotExist')));
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsNotWritable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0200);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_writable($this->fileInRoot->url());

        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows does not honor the group/other perms
            assertTrue($actual);
        } else {
            assertFalse($actual);
        }
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsWritable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0202);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_writable($this->fileInRoot->url());

        assertTrue($actual);
    }

    /**
     * @test
     */
    public function nonExistingIsNotExecutable(): void
    {
        assertFalse(is_executable(vfsStream::url('doesNotExist')));
    }

    /**
     * @test
     */
    public function isNotExecutableByDefault(): void
    {
        assertFalse(is_executable($this->fileInSubdir->url()));
    }

    /**
     * @test
     */
    public function isExecutableWithCorrectPermission(): void
    {
        $this->fileInSubdir->chmod(0766);
        assertTrue(is_executable($this->fileInSubdir->url()));
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsNotExecutable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0100);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_executable($this->fileInRoot->url());

        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows does not honor the group/other perms
            assertTrue($actual);
        } else {
            assertFalse($actual);
        }
    }

    /**
     * @test
     * @group issue_167
     */
    public function fileNotOwnedByUserOrGroupIsExecutable(): void
    {
        $this->root->chown(vfsStream::getCurrentUser());
        $this->root->chgrp(vfsStream::getCurrentGroup());

        $this->fileInRoot->chmod(0101);
        $this->fileInRoot->chown(vfsStream::getCurrentUser() + 1);
        $this->fileInRoot->chgrp(vfsStream::getCurrentGroup() + 1);

        $actual = is_executable($this->fileInRoot->url());

        assertTrue($actual);
    }

    /**
     * @test
     */
    public function directoriesAreSometimesExecutable(): void
    {
        $this->root->chmod(0766);
        // Inconsistent behavior has been fixed in 7.3
        // see https://github.com/php/php-src/commit/94b4abdbc4d
        if (PHP_VERSION_ID >= 70300) {
            assertTrue(is_executable($this->root->url()));
            assertTrue(is_executable($this->root->url() . '/.'));
        } else {
            assertFalse(is_executable($this->root->url()));
            assertFalse(is_executable($this->root->url() . '/.'));
        }
    }

    /**
     * @test
     * @dataProvider  elements
     * @group  permissions
     */
    public function filePermissionsAreReturned(string $element, int $permissions): void
    {
        assertThat(decoct(fileperms($this->$element->url())), equals($permissions));
        assertThat(decoct(fileperms($this->$element->url() . '/.')), equals($permissions));
    }

    /**
     * @test
     * @group  permissions
     */
    public function filePermissionsCanBeChanged(): void
    {
        $this->root->chmod(0755);
        assertThat(decoct(fileperms($this->root->url())), equals(40755));
    }

    /**
     * @test
     * @group  issue_11
     * @group  permissions
     */
    public function chmodModifiesPermissions(): void
    {
        assertTrue(chmod($this->root->url(), 0755));
        assertThat(decoct(fileperms($this->root->url())), equals(40755));
    }

    /**
     * @test
     * @group  issue_11
     * @group  permissions
     */
    public function chownChangesUser(): void
    {
        assertTrue(chown($this->root->url(), vfsStream::OWNER_USER_1));
        assertThat(fileowner($this->root->url()), equals(vfsStream::OWNER_USER_1));
        assertThat(fileowner($this->root->url() . '/.'), equals(vfsStream::OWNER_USER_1));
    }

    /**
     * @test
     * @group  issue_11
     * @group  permissions
     */
    public function chgrpChangesGroup(): void
    {
        assertTrue(chgrp($this->root->url(), vfsStream::GROUP_USER_1));
        assertThat(filegroup($this->root->url()), equals(vfsStream::GROUP_USER_1));
        assertThat(filegroup($this->root->url() . '/.'), equals(vfsStream::GROUP_USER_1));
    }

    /**
     * @return string[][]
     */
    public function targets(): array
    {
        return [
            [vfsStream::url('root/subdir'), vfsStream::url('root/baz3')],
            [vfsStream::url('root/subdir/.'), vfsStream::url('root/baz3')],
            [vfsStream::url('root/subdir'), vfsStream::url('root/../baz3/.')],
        ];
    }

    /**
     * @test
     * @dataProvider targets
     * @group  issue_9
     */
    public function renameDirectory(string $source, string $target): void
    {
        assertTrue(rename($source, $target));
        assertThat($target, isExistingDirectory());
        assertThat($source, isNonExistingDirectory());
    }

    /**
     * @test
     */
    public function renameDirectoryOverwritingExistingFile(): void
    {
        // move root/subdir to root/file2
        $oldURL = $this->subdir->url();
        assertTrue(rename($oldURL, $this->fileInRoot->url()));
        assertThat(vfsStream::url('root/file2/file1'), isExistingFile());
        assertThat($oldURL, isNonExistingDirectory());
    }

    /**
     * @test
     */
    public function renameFileIntoFileTriggersWarningAndDoesNotChangeFiles(): void
    {
        // root/file2 is a file, so it can not be turned into a directory
        $oldURL = $this->fileInSubdir->url();
        $baz3URL = vfsStream::url('root/file2/baz3');
        expect(function () use ($baz3URL): void {
            assertTrue(rename($this->fileInSubdir->url(), $baz3URL));
        })->triggers(E_USER_WARNING)
          ->after($baz3URL, isNonExistingFile())
          ->after($oldURL, isExistingFile());
    }

    /**
     * @test
     */
    public function moveFileToAnotherDirectoryDirectory(): void
    {
        // move root/subdir/file1 to root/baz3
        $oldURL = $this->fileInSubdir->url();
        $baz3URL = vfsStream::url('root/baz3');
        assertTrue(rename($this->fileInSubdir->url(), $baz3URL));
        assertThat($baz3URL, isExistingFile());
        assertThat($oldURL, isNonExistingFile());
    }

    /**
     * @test
     */
    public function moveFileToAnotherDirectoryDoesNotChangeExistingDirectory(): void
    {
        // move root/subdir/file1 to root/baz3
        assertTrue(rename($this->fileInSubdir->url(), $this->root->url() . '/baz3'));
        assertThat($this->subdir->url(), isExistingDirectory());
    }

    /**
     * @test
     */
    public function renameNonExistingFileTriggersWarning(): void
    {
        expect(function (): void {
            rename(vfsStream::url('doesNotExist'), $this->fileInSubdir->url());
        })->triggers(E_USER_WARNING);
    }

    /**
     * @test
     */
    public function renameIntoNonExistingDirectoryTriggersWarning(): void
    {
        expect(function (): void {
            rename($this->fileInSubdir->url(), vfsStream::url('root/doesNotExist/file2'));
        })->triggers(E_USER_WARNING);
    }

    /**
     * @test
     */
    public function statAndFstatReturnSameResult(): void
    {
        $fp = fopen($this->fileInRoot->url(), 'r');
        assertThat(stat($this->fileInRoot->url()), equals(fstat($fp)));
        fclose($fp);
    }

    /**
     * @test
     */
    public function statReturnsFullDataForFiles(): void
    {
        $this->fileInRoot->lastModified(400)
            ->lastAccessed(400)
            ->lastAttributeModified(400);
        assertThat(
            stat($this->fileInRoot->url()),
            equals([
                0 => 0,
                1 => spl_object_id($this->fileInRoot),
                2 => 0100666,
                3 => 0,
                4 => vfsStream::getCurrentUser(),
                5 => vfsStream::getCurrentGroup(),
                6 => 0,
                7 => 6,
                8 => 400,
                9 => 400,
                10 => 400,
                11 => -1,
                12 => -1,
                'dev' => 0,
                'ino' => spl_object_id($this->fileInRoot),
                'mode' => 0100666,
                'nlink' => 0,
                'uid' => vfsStream::getCurrentUser(),
                'gid' => vfsStream::getCurrentGroup(),
                'rdev' => 0,
                'size' => 6,
                'atime' => 400,
                'mtime' => 400,
                'ctime' => 400,
                'blksize' => -1,
                'blocks' => -1,
            ])
        );
    }

    /**
     * @test
     */
    public function statReturnsFullDataForDirectories(): void
    {
        $this->root->lastModified(100)
            ->lastAccessed(100)
            ->lastAttributeModified(100);
        assertThat(
            stat($this->root->url()),
            equals([
                0 => 0,
                1 => spl_object_id($this->root),
                2 => 0040777,
                3 => 0,
                4 => vfsStream::getCurrentUser(),
                5 => vfsStream::getCurrentGroup(),
                6 => 0,
                7 => 0,
                8 => 100,
                9 => 100,
                10 => 100,
                11 => -1,
                12 => -1,
                'dev' => 0,
                'ino' => spl_object_id($this->root),
                'mode' => 0040777,
                'nlink' => 0,
                'uid' => vfsStream::getCurrentUser(),
                'gid' => vfsStream::getCurrentGroup(),
                'rdev' => 0,
                'size' => 0,
                'atime' => 100,
                'mtime' => 100,
                'ctime' => 100,
                'blksize' => -1,
                'blocks' => -1,
            ])
        );
    }

    /**
     * @test
     */
    public function statReturnsFullDataForDirectoriesWithDot(): void
    {
        $this->root->lastModified(100)
          ->lastAccessed(100)
          ->lastAttributeModified(100);
        assertThat(
            stat($this->root->url() . '/.'),
            equals([
                0 => 0,
                1 => spl_object_id($this->root),
                2 => 0040777,
                3 => 0,
                4 => vfsStream::getCurrentUser(),
                5 => vfsStream::getCurrentGroup(),
                6 => 0,
                7 => 0,
                8 => 100,
                9 => 100,
                10 => 100,
                11 => -1,
                12 => -1,
                'dev' => 0,
                'ino' => spl_object_id($this->root),
                'mode' => 0040777,
                'nlink' => 0,
                'uid' => vfsStream::getCurrentUser(),
                'gid' => vfsStream::getCurrentGroup(),
                'rdev' => 0,
                'size' => 0,
                'atime' => 100,
                'mtime' => 100,
                'ctime' => 100,
                'blksize' => -1,
                'blocks' => -1,
            ])
        );
    }

    /**
     * @test
     */
    public function openFileWithoutDirectory(): void
    {
        vfsStreamWrapper::register();
        expect(static function (): void {
            assertFalse(file_get_contents(vfsStream::url('file.txt')));
        })->triggers(E_WARNING);
    }

    /**
     * @test
     * @group     issue_33
     */
    public function truncateRemovesSuperflouosContent(): void
    {
        $handle = fopen($this->fileInSubdir->url(), 'r+');
        assertTrue(ftruncate($handle, 0));
        assertEmptyString(file_get_contents($this->fileInSubdir->url()));
        fclose($handle);
    }

    /**
     * @test
     * @group     issue_33
     */
    public function truncateToGreaterSizeAddsZeroBytes(): void
    {
        $handle = fopen($this->fileInSubdir->url(), 'r+');
        assertTrue(ftruncate($handle, 25));
        fclose($handle);
        assertThat(
            file_get_contents($this->fileInSubdir->url()),
            equals("file 1\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0")
        );
    }

    /**
     * @test
     * @group     issue_11
     */
    public function touchCreatesNonExistingFile(): void
    {
        assertTrue(touch($this->root->url() . '/new.txt'));
        assertTrue($this->root->hasChild('new.txt'));
    }

    /**
     * @test
     * @group     issue_11
     */
    public function touchChangesAccessAndModificationTimeForFile(): void
    {
        assertTrue(touch($this->fileInSubdir->url(), 303, 313));
        assertThat($this->fileInSubdir->filemtime(), equals(303));
        assertThat($this->fileInSubdir->fileatime(), equals(313));
    }

    /**
     * @test
     * @group     issue_11
     * @group     issue_80
     */
    public function touchChangesTimesToCurrentTimestampWhenNoTimesGiven(): void
    {
        assertTrue(touch($this->fileInSubdir->url()));
        assertThat($this->fileInSubdir->filemtime(), equals(time(), 1));
        assertThat($this->fileInSubdir->fileatime(), equals(time(), 1));
    }

    /**
     * @test
     * @group     issue_11
     */
    public function touchWithModifiedTimeChangesAccessAndModifiedTime(): void
    {
        assertTrue(touch($this->fileInSubdir->url(), 303));
        assertThat($this->fileInSubdir->filemtime(), equals(303));
        assertThat($this->fileInSubdir->fileatime(), equals(303));
    }

    /**
     * @test
     * @group     issue_11
     */
    public function touchChangesAccessAndModificationTimeForDirectory(): void
    {
        assertTrue(touch($this->root->url(), 303, 313));
        assertThat($this->root->filemtime(), equals(303));
        assertThat($this->root->fileatime(), equals(313));
    }

    /**
     * @return mixed[][]
     */
    public function elements(): array
    {
        return [
            ['root', 40777],
            ['subdir', 40777],
            ['fileInSubdir', 100666],
            ['fileInRoot', 100666],
        ];
    }

    /**
     * @test
     * @dataProvider  elements
     * @group  issue_34
     */
    public function pathesAreCorrectlySet(string $element): void
    {
        assertThat($this->$element->path(), equals(vfsStream::path($this->$element->url())));
    }

    /**
     * @test
     * @group  issue_34
     */
    public function pathIsUpdatedAfterMove(): void
    {
        $baz3URL = vfsStream::url('root/baz3');
        // move root/subdir/file1 to root/baz3
        assertTrue(rename($this->fileInSubdir->url(), $baz3URL));
        assertThat($this->fileInSubdir->path(), equals(vfsStream::path($baz3URL)));
    }

    /**
     * @test
     * @group  issue_34
     */
    public function urlIsUpdatedAfterMove(): void
    {
        $baz3URL = vfsStream::url('root/baz3');
        // move root/subdir/file1 to root/baz3
        assertTrue(rename($this->fileInSubdir->url(), $baz3URL));
        assertThat($this->fileInSubdir->url(), equals($baz3URL));
    }

    /**
     * @test
     */
    public function fileCopy(): void
    {
        $baz3URL = vfsStream::url('root/baz3');
        assertTrue(copy($this->fileInSubdir->url(), $baz3URL));
        assertTrue($this->root->hasChild('baz3'));
        assertThat($baz3URL, isNotEqualTo($this->fileInSubdir->url()));
    }

    /**
     * @test
     */
    public function multipleReadsOnSameFileHaveDifferentPointers(): void
    {
        $content = uniqid();
        $this->fileInSubdir->setContent($content);

        $fp1 = fopen($this->fileInSubdir->url(), 'rb');
        $fp2 = fopen($this->fileInSubdir->url(), 'rb');

        assertThat(fread($fp1, 4096), equals($content));
        assertThat(fread($fp2, 4096), equals($content));

        fclose($fp1);
        fclose($fp2);
    }

    /**
     * @test
     */
    public function multipleWritesOnSameFileHaveDifferentPointers(): void
    {
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        $url = $this->fileInSubdir->url();

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
        $url = $this->fileInSubdir->url();

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
    public function feofIsFalseWhenEmptyFileOpened(): void
    {
        $this->fileInSubdir->setContent('');

        $stream = fopen($this->fileInSubdir->url(), 'r');

        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsTrueAfterEmptyFileRead(): void
    {
        $this->fileInSubdir->setContent('');

        $stream = fopen($this->fileInSubdir->url(), 'r');

        fgets($stream);

        assertTrue(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseWhenEmptyStreamRewound(): void
    {
        $this->fileInSubdir->setContent('');

        $stream = fopen($this->fileInSubdir->url(), 'r');

        fgets($stream);
        rewind($stream);
        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsFalseAfterReadingLastLine(): void
    {
        $this->fileInSubdir->setContent("Line 1\n");

        $stream = fopen($this->fileInSubdir->url(), 'r');

        fgets($stream);

        assertFalse(feof($stream));
    }

    /**
     * @test
     */
    public function feofIsTrueAfterReadingBeyondLastLine(): void
    {
        $this->fileInSubdir->setContent("Line 1\n");

        $stream = fopen($this->fileInSubdir->url(), 'r');

        fgets($stream);
        fgets($stream);

        assertTrue(feof($stream));
    }
}
