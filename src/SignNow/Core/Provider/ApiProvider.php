<?php

declare(strict_types=1);

namespace SignNow\Core\Provider;

use RuntimeException;
use SignNow\ApiClient;
use GuzzleHttp\Client as HttpClient;
use SignNow\Core\Config\ConfigRepository;
use SignNow\Core\Request\EndpointResolver;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use SignNow\Core\Serializer\TypedCollectionNormalizer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use SignNow\Core\Response\ResponseToEntityMapper as ResponseMapper;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

readonly class ApiProvider
{
    public function __construct(
        private ContainerBuilder $container,
        private array $config,
    ) {
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    public function register(): void
    {
        $this->buildConfig();
        $this->registerApiClient();
        $this->compile();
    }

    public function registerApiClient(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->container
            ->autowire('serializer', Serializer::class)
            ->setArgument(
                '$normalizers',
                [
                    new TypedCollectionNormalizer(),
                    new ArrayDenormalizer(),
                    new ObjectNormalizer(
                        $classMetadataFactory,
                        new MetadataAwareNameConverter(
                            $classMetadataFactory,
                            new CamelCaseToSnakeCaseNameConverter()
                        )
                    ),
                ]
            )
            ->setArgument(
                '$encoders',
                [
                    new JsonEncoder(),
                ]
            )
            ->setPublic(true);

        $this->container
            ->register('http_client', HttpClient::class)
            ->setPublic(true);
        $this->container
            ->register('resolver', EndpointResolver::class)
            ->setPublic(true);
        $this->container
            ->register('mapper', ResponseMapper::class)
            ->addArgument(new Reference('serializer'))
            ->setPublic(true);

        $this->container->autowire('api_client', ApiClient::class)
            ->addArgument(new Reference('http_client'))
            ->addArgument(new Reference('resolver'))
            ->addArgument(new Reference('config'))
            ->addArgument(new Reference('mapper'))
            ->addArgument(new Reference('basic_token'))
            ->setPublic(true);
    }

    private function compile(): void
    {
        $this->container->compile();
    }

    /**
     * @throws RuntimeException
     */
    private function buildConfig(): void
    {
        if (!is_array($this->config)) {
            throw new RuntimeException(
                'Config must be an array.'
            );
        }

        $config = new ConfigRepository($this->config);
        $this->container
            ->set('config', $config);

        $this->container
            ->set('basic_token', $config->basicToken());
    }
}
