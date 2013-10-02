#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

if (!file_exists(__DIR__.'/config.yml')) {
    throw new RuntimeException('The configuration file config.yml does not exist. Please, read the README.');
}

$config = Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__.'/config.yml'));

$container = new Pimple($config['parameters']);

$container['create_dir'] = $container->protect(function ($directory) {
    if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
        throw new \InvalidArgumentException(sprintf(
            'The directory "%s" does not exist and could not be created.',
            $directory
        ));
    }

    if (!is_writable($directory)) {
        throw new \InvalidArgumentException(sprintf(
            'The directory "%s" is not writable.',
            $directory
        ));
    }
});

$container['cache_dir.object'] = $container->share(function ($container) {
    $directory = sys_get_temp_dir().'/github-graph/object';

    $container['create_dir']($directory);

    return $directory;
});

$container['github.cache.object'] = $container->share(function ($container) {
    return new Doctrine\Common\Cache\FilesystemCache($container['cache_dir.object']);
});

$container['github.client'] = $container->share(function ($container) {
    $client = new Github\Client();
    $client->authenticate($container['github_api_token'], null, Github\Client::AUTH_HTTP_TOKEN);

    return $client;
});

$container['github'] = $container->share(function ($container) {
    return new Lyrixx\GithubGraph\Github\Github($container['github.client'], $container['github.cache.object']);
});

$container['issue.utilily'] = $container->share(function ($container) {
    return new Lyrixx\GithubGraph\Model\IssueUtility($container['graphite.api']);
});

$container['graphite.api'] = $container->share(function ($container) {
    return new Lyrixx\GithubGraph\Graphite\Api($container['graphite']['prefix'], $container['graphite']['host'], $container['graphite']['port'], $container['graphite']['protocol']);
});

$application = new Symfony\Component\Console\Application('GithubGraph', 0.1);
$application->add(new Lyrixx\GithubGraph\Commands\AnalyzeRepositoryCommand($container['github'], $container['issue.utilily']));
$application->run();