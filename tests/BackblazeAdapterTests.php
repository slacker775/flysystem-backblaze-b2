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
        $apiKeyId = '000b5d60d2756930000000002';
        $apiKey = 'K000aYRLo66RDGg/2iTHS9BkqX/UKM4';
        $bucketId = '1bc51dd6900d920775d60913';
        $client = new ApiClient($apiKeyId, $apiKey);
        return new BackblazeB2Adapter(
            $client, $bucketId
        );
    }

}