<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/symfony3.php';

// If you would like to use Slack integration, there are two options:

// 1.
set('slack', [
    'token'    => 'Your-Token',
    'team'     => 'YourTeam',
    'channel'  => '#your-channel'
]);
require 'recipe/slack-message.php';

// 2.
set('slack_webhook', 'YourWebHookUrl');
require 'recipe/slack-webhook.php';

// Finally, include this file to force you into selecting a branch or a tag
require 'recipe/require-branch-tag.php';

// Optionally, include this in order to clear opcache
// You will also need to copy the `opcache.php` file to the root of your project
// use set('clear_opcache_php_sock', '/path/to/php/sock') to change the location of your php sock
require 'recipe/clear-opcache.php';

set('application', 'Your Application Name');
set('repository', 'git@your-repo.com/some/repository.git');

set('keep_releases', 5);
set('writable_dirs', []);

inventory('app/config/deployer.yml');