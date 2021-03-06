<?php declare(strict_types=1);

namespace Slacker775\Flysystem;

use Backblaze\B2\ApiClient;
use Backblaze\B2\Exception\BackblazeB2Exception;
use Backblaze\B2\Exception\NotFoundException;
use Backblaze\B2\Model\File;
use GuzzleHttp\Psr7\StreamWrapper;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;

class BackblazeB2Adapter implements FilesystemAdapter
{

    private PathPrefixer $prefixer;

    private ?string $bucketName;

    private MimeTypeDetector $mimeTypeDetector;

    public function __construct(private ApiClient $client,
        private string $bucketId, private string $prefix = '',
        MimeTypeDetector $mimeTypeDetector = null
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector
            ?: new FinfoMimeTypeDetector();
        $this->bucketName = null;
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->client->getFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketId
                ) !== null;
        } catch (NotFoundException $e) {
            return false;
        } catch (BackblazeB2Exception $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents,
        Config $config
    ): void {
        try {
            $this->client->uploadFile(
                $this->prefixer->stripPrefix($path), $this->bucketId, $contents
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToWriteFile::atLocation($path, '', $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents,
        Config $config
    ): void {
        try {
            $mimeType = $this->mimeTypeDetector->detectMimeType(
                $path, $contents
            );
            $this->client->uploadFile(
                $this->prefixer->stripPrefix($path), $this->bucketId, $contents,
                ['contentType' => $mimeType]
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToWriteFile::atLocation($path, '', $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        if ($this->bucketName === null) {
            $buckets = $this->client->listBuckets(null, $this->bucketId);
            $this->bucketName = $buckets[0]->getBucketName();
        }

        try {
            return (string)$this->client->downloadFileByName(
                $this->prefixer->stripPrefix($path), $this->bucketName
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToReadFile::fromLocation($path, '', $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        if ($this->bucketName === null) {
            $buckets = $this->client->listBuckets(null, $this->bucketId);
            $this->bucketName = $buckets[0]->getBucketName();
        }

        try {
            return StreamWrapper::getResource(
                $this->client->downloadFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketName
                )
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToReadFile::fromLocation($path, '', $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->client->deleteFile(
                null, $this->prefixer->stripPrefix($path), $this->bucketId
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToDeleteDirectory::atLocation($path, '', $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path,
        Config $config
    ): void {
        try {
            $this->client->uploadFile(
                $this->prefixer->stripPrefix($path), $this->bucketId, ''
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToCreateDirectory::atLocation($path);
        }
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation(
            $path, 'Operation not supported'
        );
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            if (($file = $this->client->getFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketId
                )) !== null
            ) {
                $metadata = $this->fileToFileAttributes($file);
            } else {
                throw  UnableToRetrieveMetadata::visibility($path);
            }
        } catch (BackblazeB2Exception $e) {
            throw UnableToRetrieveMetadata::visibility($path);
        }
        return $metadata;
    }

    #[Pure]
    private function fileToFileAttributes(File $file): FileAttributes
    {
        /**
         * FIXME - sort out public/private and timestamps
         */
        $timestamp = 0;
        if (isset(($file->getFileInfo())['src_last_modified_millis'])) {
            $timestamp = (int)ceil(
                ($file->getFileInfo())['src_last_modified_millis'] / 1000
            );
        }
        return new FileAttributes(
            $file->getFileName(),
            $file->getContentLength(),
            'public',
            $timestamp,
            $file->getContentType(),
            [
                'sha1' => $file->getContentSha1(),
                'fileId' => $file->getFileId(),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            if (($file = $this->client->getFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketId
                )) !== null
            ) {
                $metadata = $this->fileToFileAttributes($file);
            } else {
                throw  UnableToRetrieveMetadata::mimeType($path);
            }
        } catch (BackblazeB2Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }
        return $metadata;
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            if (($file = $this->client->getFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketId
                )) !== null
            ) {
                $metadata = $this->fileToFileAttributes($file);
            } else {
                throw  UnableToRetrieveMetadata::lastModified($path);
            }
        } catch (BackblazeB2Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }
        return $metadata;
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            if (($file = $this->client->getFileByName(
                    $this->prefixer->stripPrefix($path), $this->bucketId
                )) !== null
            ) {
                $metadata = $this->fileToFileAttributes($file);
            } else {
                throw  UnableToRetrieveMetadata::fileSize($path);
            }
        } catch (BackblazeB2Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }
        return $metadata;
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        if ($deep === true && $path === '') {
            $regex = '/^.*$/';
        } elseif ($deep === true && $path !== '') {
            $regex = '/^' . preg_quote(
                    $this->prefixer->stripDirectoryPrefix($path), '/'
                ) . "\/.*$/";
        } elseif ($deep === false && $path === '') {
            $regex = '/^(?!.*\\/).*$/';
        } elseif ($deep === false && $path !== '') {
            $regex = '/^' . preg_quote(
                    $this->prefixer->stripDirectoryPrefix($path), '/'
                )
                . '\/(?!.*\\/).*$/';
        } else {
            throw new InvalidArgumentException();
        }

        foreach (
            $this->client->listFilenames($this->bucketId, 100, $path, '/') as
            $file
        ) {
            switch ($file->getAction()) {
                case 'upload':
                    if (preg_match($regex, $file->getFileName()) === 1) {
                        yield $this->fileToFileAttributes($file);
                    }
                    break;
                case 'folder':
                    yield new DirectoryAttributes(
                        $file->getFileName(), 'public'
                    );
                    if ($deep) {
                        yield from $this->listContents(
                            $file->getFileName(), $deep
                        );
                    }
                    break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination,
        Config $config
    ): void {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (BackblazeB2Exception $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination,
        Config $config
    ): void {
        try {
            $sourceFile = $this->client->getFileByName(
                $source, $this->bucketId
            );

            $this->client->copyFile(
                $sourceFile->getFileId(),
                $this->prefixer->stripPrefix($destination)
            );

        } catch (BackblazeB2Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        try {
            $this->client->deleteFile(
                null, $this->prefixer->stripPrefix($path), $this->bucketId
            );
        } catch (BackblazeB2Exception $e) {
            throw UnableToDeleteFile::atLocation($path, '', $e);
        }
    }

}