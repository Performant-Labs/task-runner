<?php

namespace PL\Robo;

use Psr\Container\ContainerInterface;
use Robo\Robo;

/**
 * A bunch of static methods that extend corresponding Robo methods.
 */
class Testor
{
    /**
     * @return ContainerInterface|\League\Container\Container
     */
    static function createContainer(): ContainerInterface|\League\Container\Container
    {
        $container = Robo::createDefaultContainer();

        // TestorConfig (which is different from Config. Config has task and
        // its default arguments configuration, while TestorConig contains
        // common configuration for the domain which Testor is used for.)
        // TestorConfig will be initialized once and for all.
        $testorConfig = Robo::createConfiguration(['.testor.yml', '.testor_secret.yml']);
        Robo::addShared($container, 'testorConfig', $testorConfig);

        // Register Testor-specific services.
        Robo::addShared($container, 's3Client', \Aws\S3\S3Client::class)
            ->addArgument($testorConfig->get('s3.config'));
        Robo::addShared($container, 's3Bucket', $testorConfig->get('s3.bucket'));

        // Register Testor-specific inflectors.
        $container->inflector(\PL\Robo\Contract\TestorConfigAwareInterface::class)
            ->invokeMethod('setTestorConfig', ['testorConfig']);
        $container->inflector(\PL\Robo\Contract\S3ClientAwareInterface::class)
            ->invokeMethod('setS3Client', ['s3Client']);
        $container->inflector(\PL\Robo\Contract\S3BucketAwareInterface::class)
            ->invokeMethod('setS3Bucket', ['s3Bucket']);

        // Robo requires this for some reason.
        Robo::finalizeContainer($container);

        return $container;
    }
}