<?php declare(strict_types=1);

namespace Slacker775\Flysystem\Tests;

use Backblaze\B2\ApiClient;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\FilesystemAdapter;
use Slacker775\Flysystem\BackblazeB2Adapter;

class BackblazeAdapterTests
    extends FilesystemAdapterTestCase
{

    protected static function createFilesystemAdapter(
    ): FilesystemAdapter
    {
        $apiKeyId = getenv('BACKBLAZE_API_KEY_ID');
        $apiKey = getenv('BACKBLAZE_API_KEY');
        $bucketId = getenv('BACKBLAZE_BUCKET_ID');
        $client = new ApiClient($apiKeyId, $apiKey);
        return new BackblazeB2Adapter(
            $client, $bucketId
        );
    }

    /**
     * @test
     */
    public function setting_visibility(): void
    {
        self::markTestSkipped('Backblaze does not support visibility');
    }

    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        self::markTestSkipped('Backblaze does not support visibility');
    }

}