<?php

namespace Deployer;

use Deployer\Utility\Httpie;

after('deploy:lock', 'slack:notify');
after('success', 'slack:notify:success');

// Do not skip slack notifications by default
set('slack_skip_notification', false);

// Set default colors - green for prod, blue for anything else
set('slack_color', function () {
    if ('prod' == get('symfony_env')) {
        return '#7CD197';
    }

    return '#4d91f7';
});
set('slack_success_color', '{{slack_color}}');

desc('Notifying Slack channel of deployment start');
task('slack:notify', function () {
    if (true === get('slack_skip_notification')) {
        return;
    }

    $config = [
        'channel'     => '#general',
        'icon'        => ':sunny:',
        'username'    => 'Deployer',
        'message'     => "Deployment to `{{host}}` on *{{stage}}* was successful\n({{release_path}})",
        'app'         => 'app-name',
        'unset_text'  => true,
        'attachments' => [
            [
                'title'    => 'Deployment initiated',
                'fallback' => 'Deployment initiated',
                'text'     => parse("{{user.name}} <{{user.email}}> has initiated a deployment\nTarget: {{target}}\nRelease: {{branch}} - {{slack.release}}"),
                'color'    => get('slack_color')
            ],
        ],
    ];

    sendSlackMessage($config);
});

desc('Notifying Slack channel of deployment success');
task('slack:notify:success', function () {
    if (true === get('slack_skip_notification')) {
        return;
    }

    $revision = get('git.revision');
    $serverUser = get('server.user');
    $user = get('user.name');
    $email = get('user.email');
    $branch = get('branch');
    $release = get('slack.release');

    $config = [
        'channel'     => '#general',
        'icon'        => ':sunny:',
        'username'    => 'Deployer',
        'message'     => "Deployment to `{{host}}` on *{{stage}}* was successful\n({{release_path}})",
        'app'         => 'app-name',
        'unset_text'  => true,
        'attachments' => [
            [
                'text'     => sprintf(
                    'Revision %s deployed to %s by %s',
                    substr($revision, 0, 6),
                    $serverUser,
                    $user
                ),
                'title'    => 'Deployment Complete',
                'fallback' => sprintf('Deployment to %s complete.', $serverUser),
                'color'    => get('slack_success_color'),
                'fields'   => [
                    [
                        'title' => 'User',
                        'value' => $user,
                        'short' => true,
                    ],
                    [
                        'title' => 'Email',
                        'value' => $email,
                        'short' => true,
                    ],
                    [
                        'title' => 'Host',
                        'value' => get('hostname'),
                        'short' => true,
                    ],
                    [
                        'title' => 'Environment',
                        'value' => $serverUser,
                        'short' => true,
                    ],
                    [
                        'title' => 'Tag / Branch',
                        'value' => $branch,
                        'short' => true,
                    ],
                    [
                        'title' => 'Release',
                        'value' => $release,
                        'short' => true,
                    ],
                ],
            ],
        ],
    ];

    sendSlackMessage($config);
})->desc('Notifying Slack channel of deployment');

function sendSlackMessage(array $config) {
    $newConfig = get('slack');

    if (is_callable($newConfig)) {
        $config = $newConfig($config);
    } else {
        $config = array_merge($config, (array)$newConfig);
    }

    if (!is_array($config) || !isset($config['token']) || !isset($config['team']) || !isset($config['channel'])) {
        throw new \RuntimeException("Please configure new slack: set('slack', ['token' => 'xoxp...', 'team' => 'team', 'channel' => '#channel', 'messsage' => 'message to send']);");
    }

    $user = trim(run('whoami'));

    $messagePlaceHolders = [
        '{{host}}'     => get('hostname'),
        '{{stage}}'    => get('server.user'),
        '{{user}}'     => $user,
        '{{branch}}'   => get('branch'),
        '{{app_name}}' => isset($config['app']) ? $config['app'] : 'app-name',
    ];
    $config['message'] = strtr($config['message'], $messagePlaceHolders);

    $urlParams = [
        'channel'    => $config['channel'],
        'token'      => $config['token'],
        'text'       => $config['message'],
        'username'   => $config['username'],
        'icon_emoji' => $config['icon'],
        'pretty'     => true,
    ];

    foreach (['unset_text' => 'text', 'icon_url' => 'icon_emoji'] as $set => $unset) {
        if (isset($config[$set])) {
            unset($urlParams[$unset]);
        }
    }

    foreach (['parse', 'link_names', 'icon_url', 'unfurl_links', 'unfurl_media', 'as_user'] as $option) {
        if (isset($config[$option])) {
            $urlParams[$option] = $config[$option];
        }
    }

    if (isset($config['attachments'])) {
        $urlParams['attachments'] = json_encode($config['attachments']);
    }

    $result = Httpie::get('https://slack.com/api/chat.postMessage')->query($urlParams)->send();

    $response = @json_decode($result);

    if (!$response) {
        throw new \RuntimeException(sprintf('Slack returned bad Response: %s', $result));
    }

    if (isset($response->error)) {
        throw new \RuntimeException(sprintf('Slack error: %s', $response->error));
    }

}