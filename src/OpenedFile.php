<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs;

use const SEEK_SET;

/**
 * Decorator for vfsStreamFile to allow multiple instances of a file to be open.
 *
 * It works by tracking and restoring the position in the file for each specific
 * instance created, even though the underlying file is shared.
 *
 * @internal
 */
final class OpenedFile
{
    /** @var vfsStreamFile */
    private $base;

    /** @var int */
    private $position = 0;

    public function __construct(vfsStreamFile $base)
    {
        $this->base = $base;
    }

    public function getBaseFile(): vfsStreamFile
    {
        return $this->base;
    }

    /**
     * simply open the file
     */
    public function open(): void
    {
        $this->base->open();
    }

    /**
     * open file and set pointer to end of file
     */
    public function openForAppend(): void
    {
        $this->base->openForAppend();
        $this->savePosition();
    }

    /**
     * open file and truncate content
     */
    public function openWithTruncate(): void
    {
        $this->base->openWithTruncate();
        $this->savePosition();
    }

    /**
     * reads the given amount of bytes from content
     */
    public function read(int $count): string
    {
        $this->restorePosition();
        $data = $this->base->read($count);
        $this->savePosition();

        return $data;
    }

    /**
     * returns the content until its end from current offset
     */
    public function readUntilEnd(): string
    {
        $this->restorePosition();
        $data = $this->base->readUntilEnd();
        $this->savePosition();

        return $data;
    }

    /**
     * writes an amount of data
     *
     * @return  int number of bytes written
     */
    public function write(string $data): int
    {
        $this->restorePosition();
        $bytes = $this->base->write($data);
        $this->savePosition();

        return $bytes;
    }

    /**
     * Truncates a file to a given length
     *
     * @param int $size length to truncate file to
     */
    public function truncate(int $size): bool
    {
        $this->restorePosition();

        return $this->base->truncate($size);
    }

    /**
     * checks whether pointer is at end of file
     */
    public function eof(): bool
    {
        $this->restorePosition();

        return $this->base->eof();
    }

    /**
     * returns the current position within the file
     */
    public function getBytesRead(): int
    {
        $this->restorePosition();

        $this->position = $this->base->getBytesRead();

        return $this->position;
    }

    /**
     * seeks to the given offset
     */
    public function seek(int $offset, int $whence): bool
    {
        if ($whence !== SEEK_SET) {
            $this->restorePosition();
        }

        $success = $this->base->seek($offset, $whence);
        $this->savePosition();

        return $success;
    }

    /**
     * returns size of content
     */
    public function size(): int
    {
        return $this->base->size();
    }

    /**
     * locks file
     *
     * @param resource|vfsStreamWrapper $resource
     */
    public function lock($resource, int $operation): bool
    {
        return $this->base->lock($resource, $operation);
    }

    /**
     * returns the type of the container
     */
    public function getType(): int
    {
        return $this->base->getType();
    }

    /**
     * returns the last modification time of the stream content
     */
    public function filemtime(): int
    {
        return $this->base->filemtime();
    }

    /**
     * returns the last access time of the stream content
     */
    public function fileatime(): int
    {
        return $this->base->fileatime();
    }

    /**
     * returns the last attribute modification time of the stream content
     */
    public function filectime(): int
    {
        return $this->base->filectime();
    }

    /**
     * returns permissions
     */
    public function getPermissions(): int
    {
        return $this->base->getPermissions();
    }

    /**
     * checks whether content is readable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function isReadable(int $user, int $group): bool
    {
        return $this->base->isReadable($user, $group);
    }

    /**
     * checks whether content is writable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function isWritable(int $user, int $group): bool
    {
        return $this->base->isWritable($user, $group);
    }

    /**
     * checks whether content is executable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function isExecutable(int $user, int $group): bool
    {
        return $this->base->isExecutable($user, $group);
    }

    /**
     * returns owner of file
     */
    public function getUser(): int
    {
        return $this->base->getUser();
    }

    /**
     * returns owner group of file
     */
    public function getGroup(): int
    {
        return $this->base->getGroup();
    }

    private function restorePosition(): void
    {
        $this->base->getContentObject()->seek($this->position, SEEK_SET, false);
    }

    private function savePosition(): void
    {
        $this->position = $this->base->getContentObject()->bytesRead();
    }
}
