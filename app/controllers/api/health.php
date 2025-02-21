<?php

use Appwrite\ClamAV\Network;
use Appwrite\Event\Event;
use Appwrite\Extend\Exception;
use Appwrite\Utopia\Response;
use Utopia\App;
use Utopia\Config\Config;
use Utopia\Database\Document;
use Utopia\Pools\Group;
use Utopia\Queue\Client;
use Utopia\Queue\Connection;
use Utopia\Registry\Registry;
use Utopia\Storage\Device;
use Utopia\Storage\Device\Local;
use Utopia\Storage\Storage;
use Utopia\Validator\Text;

App::get('/v1/health')
    ->desc('Get HTTP')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'get')
    ->label('sdk.description', '/docs/references/health/get.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->action(function (Response $response) {

        $output = [
            'name' => 'http',
            'status' => 'pass',
            'ping' => 0
        ];

        $response->dynamic(new Document($output), Response::MODEL_HEALTH_STATUS);
    });

App::get('/v1/health/version')
    ->desc('Get version')
    ->groups(['api', 'health'])
    ->label('scope', 'public')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_VERSION)
    ->inject('response')
    ->action(function (Response $response) {
        $response->dynamic(new Document([ 'version' => APP_VERSION_STABLE ]), Response::MODEL_HEALTH_VERSION);
    });

App::get('/v1/health/db')
    ->desc('Get DB')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getDB')
    ->label('sdk.description', '/docs/references/health/get-db.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->inject('pools')
    ->action(function (Response $response, Group $pools) {

        $output = [];

        $configs = [
            'Console.DB' => Config::getParam('pools-console'),
            'Projects.DB' => Config::getParam('pools-database'),
        ];

        foreach ($configs as $key => $config) {
            foreach ($config as $database) {
                try {
                    $adapter = $pools->get($database)->pop()->getResource();

                    $checkStart = \microtime(true);

                    if ($adapter->ping()) {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'pass',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    } else {
                        $failure[] = $database;
                    }
                } catch (\Throwable $th) {
                    $failure[] = $database;
                }
            }
        }

        if (!empty($failure)) {
            throw new Exception(Exception::GENERAL_SERVER_ERROR, 'DB failure on: ' . implode(", ", $failure));
        }

        $response->dynamic(new Document([
            'statuses' => $output,
            'total' => count($output),
        ]), Response::MODEL_HEALTH_STATUS_LIST);
    });

App::get('/v1/health/cache')
    ->desc('Get cache')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getCache')
    ->label('sdk.description', '/docs/references/health/get-cache.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->inject('pools')
    ->action(function (Response $response, Group $pools) {

        $output = [];

        $configs = [
            'Cache' => Config::getParam('pools-cache'),
        ];

        foreach ($configs as $key => $config) {
            foreach ($config as $database) {
                try {
                    $adapter = $pools->get($database)->pop()->getResource();

                    $checkStart = \microtime(true);

                    if ($adapter->ping()) {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'pass',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    } else {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'fail',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    }
                } catch (\Throwable $th) {
                    $output[] = new Document([
                        'name' => $key . " ($database)",
                        'status' => 'fail',
                        'ping' => \round((\microtime(true) - $checkStart) / 1000)
                    ]);
                }
            }
        }

        $response->dynamic(new Document([
            'statuses' => $output,
            'total' => count($output),
        ]), Response::MODEL_HEALTH_STATUS_LIST);
    });

App::get('/v1/health/queue')
    ->desc('Get queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueue')
    ->label('sdk.description', '/docs/references/health/get-queue.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->inject('pools')
    ->action(function (Response $response, Group $pools) {

        $output = [];

        $configs = [
            'Queue' => Config::getParam('pools-queue'),
        ];

        foreach ($configs as $key => $config) {
            foreach ($config as $database) {
                try {
                    $adapter = $pools->get($database)->pop()->getResource();

                    $checkStart = \microtime(true);

                    if ($adapter->ping()) {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'pass',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    } else {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'fail',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    }
                } catch (\Throwable $th) {
                    $output[] = new Document([
                        'name' => $key . " ($database)",
                        'status' => 'fail',
                        'ping' => \round((\microtime(true) - $checkStart) / 1000)
                    ]);
                }
            }
        }

        $response->dynamic(new Document([
            'statuses' => $output,
            'total' => count($output),
        ]), Response::MODEL_HEALTH_STATUS_LIST);
    });

App::get('/v1/health/pubsub')
    ->desc('Get pubsub')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getPubSub')
    ->label('sdk.description', '/docs/references/health/get-pubsub.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->inject('pools')
    ->action(function (Response $response, Group $pools) {

        $output = [];

        $configs = [
            'PubSub' => Config::getParam('pools-pubsub'),
        ];

        foreach ($configs as $key => $config) {
            foreach ($config as $database) {
                try {
                    $adapter = $pools->get($database)->pop()->getResource();

                    $checkStart = \microtime(true);

                    if ($adapter->ping()) {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'pass',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    } else {
                        $output[] = new Document([
                            'name' => $key . " ($database)",
                            'status' => 'fail',
                            'ping' => \round((\microtime(true) - $checkStart) / 1000)
                        ]);
                    }
                } catch (\Throwable $th) {
                    $output[] = new Document([
                        'name' => $key . " ($database)",
                        'status' => 'fail',
                        'ping' => \round((\microtime(true) - $checkStart) / 1000)
                    ]);
                }
            }
        }

        $response->dynamic(new Document([
            'statuses' => $output,
            'total' => count($output),
        ]), Response::MODEL_HEALTH_STATUS_LIST);
    });

App::get('/v1/health/time')
    ->desc('Get time')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getTime')
    ->label('sdk.description', '/docs/references/health/get-time.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_TIME)
    ->inject('response')
    ->action(function (Response $response) {

        /*
         * Code from: @see https://www.beliefmedia.com.au/query-ntp-time-server
         */
        $host = 'time.google.com'; // https://developers.google.com/time/
        $gap = 60; // Allow [X] seconds gap

        /* Create a socket and connect to NTP server */
        $sock = \socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        \socket_connect($sock, $host, 123);

        /* Send request */
        $msg = "\010" . \str_repeat("\0", 47);

        \socket_send($sock, $msg, \strlen($msg), 0);

        /* Receive response and close socket */
        \socket_recv($sock, $recv, 48, MSG_WAITALL);
        \socket_close($sock);

        /* Interpret response */
        $data = \unpack('N12', $recv);
        $timestamp = \sprintf('%u', $data[9]);

        /* NTP is number of seconds since 0000 UT on 1 January 1900
            Unix time is seconds since 0000 UT on 1 January 1970 */
        $timestamp -= 2208988800;

        $diff = ($timestamp - \time());

        if ($diff > $gap || $diff < ($gap * -1)) {
            throw new Exception(Exception::GENERAL_SERVER_ERROR, 'Server time gaps detected');
        }

        $output = [
            'remoteTime' => $timestamp,
            'localTime' => \time(),
            'diff' => $diff
        ];

        $response->dynamic(new Document($output), Response::MODEL_HEALTH_TIME);
    });

App::get('/v1/health/queue/webhooks')
    ->desc('Get webhooks queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueWebhooks')
    ->label('sdk.description', '/docs/references/health/get-queue-webhooks.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::WEBHOOK_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/logs')
    ->desc('Get logs queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueLogs')
    ->label('sdk.description', '/docs/references/health/get-queue-logs.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::AUDITS_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/certificates')
    ->desc('Get certificates queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueCertificates')
    ->label('sdk.description', '/docs/references/health/get-queue-certificates.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::CERTIFICATES_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/builds')
    ->desc('Get builds queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueBuilds')
    ->label('sdk.description', '/docs/references/health/get-queue-builds.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::BUILDS_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/databases')
    ->desc('Get databases queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueDatabases')
    ->label('sdk.description', '/docs/references/health/get-queue-databases.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->param('name', 'database_db_main', new Text(256), 'Queue name for which to check the queue size', true)
    ->inject('queue')
    ->inject('response')
    ->action(function (string $name, Connection $queue, Response $response) {
        $client = new Client($name, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/deletes')
    ->desc('Get deletes queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueDeletes')
    ->label('sdk.description', '/docs/references/health/get-queue-deletes.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::DELETE_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/mails')
    ->desc('Get mails queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueMails')
    ->label('sdk.description', '/docs/references/health/get-queue-mails.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::MAILS_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/messaging')
    ->desc('Get messaging queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueMessaging')
    ->label('sdk.description', '/docs/references/health/get-queue-messaging.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::MESSAGING_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/migrations')
    ->desc('Get migrations queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueMigrations')
    ->label('sdk.description', '/docs/references/health/get-queue-migrations.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::MIGRATIONS_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/queue/functions')
    ->desc('Get functions queue')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getQueueFunctions')
    ->label('sdk.description', '/docs/references/health/get-queue-functions.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_QUEUE)
    ->inject('queue')
    ->inject('response')
    ->action(function (Connection $queue, Response $response) {
        $client = new Client(Event::FUNCTIONS_QUEUE_NAME, $queue);
        $response->dynamic(new Document([ 'size' => $client->sumProcessingJobs() ]), Response::MODEL_HEALTH_QUEUE);
    }, ['response']);

App::get('/v1/health/storage/local')
    ->desc('Get local storage')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getStorageLocal')
    ->label('sdk.description', '/docs/references/health/get-storage-local.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_STATUS)
    ->inject('response')
    ->action(function (Response $response) {

        $checkStart = \microtime(true);

        foreach (
            [
                'Uploads' => APP_STORAGE_UPLOADS,
                'Cache' => APP_STORAGE_CACHE,
                'Config' => APP_STORAGE_CONFIG,
                'Certs' => APP_STORAGE_CERTIFICATES
            ] as $key => $volume
        ) {
            $device = new Local($volume);

            if (!\is_readable($device->getRoot())) {
                throw new Exception(Exception::GENERAL_SERVER_ERROR, 'Device ' . $key . ' dir is not readable');
            }

            if (!\is_writable($device->getRoot())) {
                throw new Exception(Exception::GENERAL_SERVER_ERROR, 'Device ' . $key . ' dir is not writable');
            }
        }

        $output = [
            'status' => 'pass',
            'ping' => \round((\microtime(true) - $checkStart) / 1000)
        ];

        $response->dynamic(new Document($output), Response::MODEL_HEALTH_STATUS);
    });

App::get('/v1/health/anti-virus')
    ->desc('Get antivirus')
    ->groups(['api', 'health'])
    ->label('scope', 'health.read')
    ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    ->label('sdk.namespace', 'health')
    ->label('sdk.method', 'getAntivirus')
    ->label('sdk.description', '/docs/references/health/get-storage-anti-virus.md')
    ->label('sdk.response.code', Response::STATUS_CODE_OK)
    ->label('sdk.response.type', Response::CONTENT_TYPE_JSON)
    ->label('sdk.response.model', Response::MODEL_HEALTH_ANTIVIRUS)
    ->inject('response')
    ->action(function (Response $response) {

        $output = [
            'status' => '',
            'version' => ''
        ];

        if (App::getEnv('_APP_STORAGE_ANTIVIRUS') === 'disabled') { // Check if scans are enabled
            $output['status'] = 'disabled';
            $output['version'] = '';
        } else {
            $antivirus = new Network(
                App::getEnv('_APP_STORAGE_ANTIVIRUS_HOST', 'clamav'),
                (int) App::getEnv('_APP_STORAGE_ANTIVIRUS_PORT', 3310)
            );

            try {
                $output['version'] = @$antivirus->version();
                $output['status'] = (@$antivirus->ping()) ? 'pass' : 'fail';
            } catch (\Exception $e) {
                throw new Exception(Exception::GENERAL_SERVER_ERROR, 'Antivirus is not available');
            }
        }

        $response->dynamic(new Document($output), Response::MODEL_HEALTH_ANTIVIRUS);
    });

App::get('/v1/health/stats') // Currently only used internally
->desc('Get system stats')
    ->groups(['api', 'health'])
    ->label('scope', 'root')
    // ->label('sdk.auth', [APP_AUTH_TYPE_KEY])
    // ->label('sdk.namespace', 'health')
    // ->label('sdk.method', 'getStats')
    ->label('docs', false)
    ->inject('response')
    ->inject('register')
    ->inject('deviceFiles')
    ->action(function (Response $response, Registry $register, Device $deviceFiles) {

        $cache = $register->get('cache');

        $cacheStats = $cache->info();

        $response
            ->json([
                'storage' => [
                    'used' => Storage::human($deviceFiles->getDirectorySize($deviceFiles->getRoot() . '/')),
                    'partitionTotal' => Storage::human($deviceFiles->getPartitionTotalSpace()),
                    'partitionFree' => Storage::human($deviceFiles->getPartitionFreeSpace()),
                ],
                'cache' => [
                    'uptime' => $cacheStats['uptime_in_seconds'] ?? 0,
                    'clients' => $cacheStats['connected_clients'] ?? 0,
                    'hits' => $cacheStats['keyspace_hits'] ?? 0,
                    'misses' => $cacheStats['keyspace_misses'] ?? 0,
                    'memory_used' => $cacheStats['used_memory'] ?? 0,
                    'memory_used_human' => $cacheStats['used_memory_human'] ?? 0,
                    'memory_used_peak' => $cacheStats['used_memory_peak'] ?? 0,
                    'memory_used_peak_human' => $cacheStats['used_memory_peak_human'] ?? 0,
                ],
            ]);
    });
