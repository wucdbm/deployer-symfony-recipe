<?php

namespace Deployer;

require 'vendor/deployer/recipes/recipe/slack.php';

// set('slack_webhook', $webHookUrl);

set('slack_color', function () {
    if ('prod' == get('symfony_env')) {
        return '#7CD197';
    }

    return '#4d91f7';
});

set('slack_text', "*{{user.name}}* _<{{user.email}}>_ has initiated a deployment\nTarget: *{{target}}*\nRelease: *{{branch}}* - _{{slack.release}}_");
after('deploy:lock', 'slack:notify');

set('slack_success_text', "*{{user.name}}* _<{{user.email}}>_ has deployed successfully!\nTarget: *{{target}}*\nRelease: *{{branch}}* - _{{slack.release}}_");
after('success', 'slack:notify:success');