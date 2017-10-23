<?php

namespace Deployer;

set('clear_opcache_php_sock', '/var/run/php/php7.1-{{user}}.sock');
task('deploy:opcache:clear', function () {
    $command = 'SCRIPT_NAME=/opcache.php SCRIPT_FILENAME={{deploy_path}}/current/opcache.php REQUEST_METHOD=GET cgi-fcgi -bind -connect {{clear_opcache_php_sock}}';
    $result = run($command);
    output()->writeln(sprintf('<info>Opcache reset status: %s</info>', $result));
});

after('deploy', 'deploy:opcache:clear');