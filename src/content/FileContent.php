<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs\content;

use function class_alias;

/**
 * Interface for actual file contents.
 *
 * @since  1.3.0
 */
interface FileContent
{
    /**
     * returns actual content
     */
    public function content(): string;

    /**
     * returns size of content
     */
    public function size(): int;

    /**
     * reads the given amount of bytes from content
     */
    public function read(int $count): string;

    /**
     * seeks to the given offset
     */
    public function seek(int $offset, int $whence, bool $resetEof = true): bool;

    /**
     * checks whether pointer is at end of file
     */
    public function eof(): bool;

    /**
     * writes an amount of data
     *
     * @return  int     amount of written bytes
     */
    public function write(string $data): int;

    /**
     * Truncates a file to a given length
     *
     * @param   int $size length to truncate file to
     */
    public function truncate(int $size): bool;

    /**
     * Returns the current position within the file.
     *
     * @internal
     */
    public function bytesRead(): int;

    /**
     * Returns the content until its end from current offset.
     *
     * Using this method changes the time when the file was last accessed.
     *
     * @internal
     */
    public function readUntilEnd(): string;
}

class_alias('bovigo\vfs\content\FileContent', 'org\bovigo\vfs\content\FileContent');
