<?php

namespace Deployer;

// Note: For cgi-fcgi to work, the below package needs to be installed on Ubuntu-based systems
// apt-get install libfcgi0ldbl

set('clear_opcache_php_sock', [
    '/var/run/php/php7.1-{{user}}.sock'
]);
task('deploy:opcache:clear', function () {
    $socks = get('clear_opcache_php_sock');

    if (!is_array($socks)) {
        $socks = [$socks];
    }

    foreach ($socks as $sock) {
        $command = sprintf('SCRIPT_NAME=/opcache.php SCRIPT_FILENAME={{deploy_path}}/current/opcache.php REQUEST_METHOD=GET cgi-fcgi -bind -connect %s', $sock);
        $result = run($command);
        output()->writeln(sprintf('<info>Opcache reset status: %s</info>', $result));
    }
});

after('deploy', 'deploy:opcache:clear');