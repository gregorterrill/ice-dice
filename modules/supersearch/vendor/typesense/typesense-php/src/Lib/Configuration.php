<?php

namespace Typesense\Lib;

use Typesense\Exceptions\ConfigError;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class Configuration
 *
 * @package \Typesense
 * @date    4/5/20
 * @author  Abdullah Al-Faqeir <abdullah@devloops.net>
 */
class Configuration
{

    /**
     * @var Node[]
     */
    private array $nodes;

    /**
     * @var Node|null
     */
    private ?Node $nearestNode;

    /**
     * @var float
     */
    private float $connectionTimeoutSeconds;

    /**
     * @var string
     */
    private string $apiKey;

    /**
     * @var float
     */
    private float $numRetries;

    /**
     * @var float
     */
    private float $retryIntervalSeconds;

    /**
     * @var int
     */
    private int $healthCheckIntervalSeconds;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var int
     */
    private int $logLevel;

    /**
     * Configuration constructor.
     *
     * @param array $config
     *
     * @throws ConfigError
     */
    public function __construct(array $config)
    {
        $this->validateConfigArray($config);

        $nodes = $config['nodes'] ?? [];

        foreach ($nodes as $node) {
            $this->nodes[] = new Node($node['host'], $node['port'], $node['path'] ?? '', $node['protocol']);
        }

        $nearestNode       = $config['nearest_node'] ?? null;
        $this->nearestNode = null;
        if (!is_null($nearestNode)) {
            $this->nearestNode =
                new Node(
                    $nearestNode['host'],
                    $nearestNode['port'],
                    $nearestNode['path'] ?? '',
                    $nearestNode['protocol']
                );
        }

        $this->apiKey = $config['api_key'] ?? '';
        $this->connectionTimeoutSeconds   = (float)($config['connection_timeout_seconds'] ?? 1.0);
        $this->healthCheckIntervalSeconds = (int)($config['healthcheck_interval_seconds'] ?? 60);
        $this->numRetries           = (float)($config['num_retries'] ?? 3);
        $this->retryIntervalSeconds = (float)($config['retry_interval_seconds'] ?? 1.0);

        $this->logLevel = $config->logLevel ?? Logger::WARNING;
        $this->logger   = new Logger('typesense');
        $this->logger->pushHandler(new StreamHandler('php://stdout', $this->logLevel));
    }

    /**
     * @param array $config
     *
     * @throws ConfigError
     */
    private function validateConfigArray(array $config): void
    {
        $nodes = $config['nodes'] ?? false;
        if (!$nodes) {
            throw new ConfigError('`nodes` is not defined.');
        }

        $apiKey = $config['api_key'] ?? false;
        if (!$apiKey) {
            throw new ConfigError('`api_key` is not defined.');
        }

        foreach ($nodes as $node) {
            if (!$this->validateNodeFields($node)) {
                throw new ConfigError(
                    '`node` entry be a dictionary with the following required keys: host, port, protocol, api_key'
                );
            }
        }
        $nearestNode = $config['nearest_node'] ?? [];
        if (!empty($nearestNode) && !$this->validateNodeFields($nearestNode)) {
            throw new ConfigError(
                '`nearest_node` entry be a dictionary with the following required keys: host, port, protocol, api_key'
            );
        }
    }

    /**
     * @param array $node
     *
     * @return bool
     */
    public function validateNodeFields(array $node): bool
    {
        $keys = [
            'host',
            'port',
            'protocol',
        ];
        return !array_diff_key(array_flip($keys), $node);
    }

    /**
     * @return Node[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return Node
     */
    public function getNearestNode(): ?Node
    {
        return $this->nearestNode;
    }

    /**
     * @return mixed|string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @return float
     */
    public function getNumRetries(): float
    {
        return $this->numRetries;
    }

    /**
     * @return float
     */
    public function getRetryIntervalSeconds(): float
    {
        return $this->retryIntervalSeconds;
    }

    /**
     * @return float
     */
    public function getConnectionTimeoutSeconds(): float
    {
        return $this->connectionTimeoutSeconds;
    }

    /**
     * @return float|mixed
     */
    public function getHealthCheckIntervalSeconds()
    {
        return $this->healthCheckIntervalSeconds;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
