<?php

namespace Deployer;

require 'recipe/common.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);

set('clear_use_sudo', true);
set('repository', 'https://github.com/4xxi/phabricator.git');
set('phabricator_bin_path', '{{deploy_path}}/current/bin');

set('shared_dirs', ['src/extensions', 'conf']);
set('shared_files', []);

desc('Reload php-fpm');
task('reload:php-fpm', function() {
    run('sudo /usr/sbin/service {{php_fpm_service_name}} reload');
});

desc('Reload phabricator daemons');
task('reload:phd', function() {
    run('sudo {{phabricator_bin_path}}/phd reload');
});

desc('Upgrade database schema');
task('schema:upgrade', function() {
    run('sudo {{phabricator_bin_path}}/storage upgrade --force');
});

desc('Restore phabricator local config');
task('deploy:copy-local-config', function() {
    run('cp -R /home/deploy/phabricator/conf {{deploy_path}}/shared/');
});

server('develop', 'dev-p.4xxi.com', 2222)
    ->user('deploy')
    ->identityFile()
    ->stage(['dev'])
    ->set('branch', 'master')
    ->set('deploy_path', '/var/www/phabricator')
    ->set('php_fpm_service_name', 'php7.1-fpm')
    ->pty(false);

task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:copy-local-config',
    'deploy:symlink',
    'reload:php-fpm',
    'schema:upgrade',
    'reload:phd',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy phabricator');

after('deploy:failed', 'deploy:unlock');
