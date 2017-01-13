<?php
// ex: ts=4 sts=4 sw=4 et:

namespace Pantheon\Lockr\Commands;

use Pantheon\Terminus\Commands\Remote\SSHBaseCommand;

/**
 * Class Lockdown
 * @package Lockr\Commands
 */
class LockdownCommand extends SSHBaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected $valid_frameworks = [
        'drupal',
        'drupal8',
        'wordpress',
    ];

    protected function callCommand($cmd, $args) {
        $this->command = $cmd;
        $this->executeCommand($args);
    }

    protected function commit($env, $msg) {
        $workflow = $env->commitChanges($msg);
        $workflow->wait();
    }

    /**
     * Install and register Lockr, and apply patches.
     *
     * @authorize
     *
     * @command lockr:lockdown
     * @aliases lockdown
     *
     * @usage terminus lockr:lockdown <site_id> <email> [<password>]
     *   Call lockdown on a site to install the Lockr modules and patch
     *   installed module for use with Key and Lockr.
     */
    public function lockdown($site_id, $email, $password=NULL)
    {
        $site_env_id = "{$site_id}.dev";
        $this->prepareEnvironment($site_env_id);

        list($site, $env) = $this->getSiteEnv($site_env_id);

        if ($env->get('connection_mode') !== 'sftp') {
            $git_mode = true;
            $env->changeConnectionMode('sftp')->wait();
        } else {
            $git_mode = false;
            if ($env->diffstat()) {
                $this->log()->warning(
                    'Note: This site has changes to files that are not yet committed. ' .
                    'If you want to install Lockr, you will need to commit these changes first.'
                );
                return;
            }
        }

        $fmwk = $site->get('framework');

        if ($fmwk === 'drupal') {
            $this->callCommand('drush', ['en', '-y', 'lockr']);
            $this->callCommand('drush', ['cc', 'drush']);
        } else {
            $this->callCommand('wp', ['plugin', 'install', 'lockr', '--activate']);
        }

        $this->commit($env, 'Lockr module installed.');

        if ($fmwk === 'drupal') {
            $this->callCommand('drush', ['lockdown']);
        } else {
            $this->callCommand('wp', ['lockr', 'lockdown']);
        }

        $this->commit($env, 'Lockr patches applied.');

        if ($fmwk === 'drupal') {
            $cmd = 'drush';
            $args = ['lockr-register', $email];
        } else {
            $cmd = 'wp';
            $args = ['wp', 'lockr', 'register', 'site', "--email={$email}"];
        }
        if ($password !== NULL) {
            $args[] = "--password={$password}";
        }
        $this->callCommand($cmd, $args);

        if ($git_mode) {
            if (!$env->diffstat()) {
                // Re-get the env to prevent "already in git mode" message
                $env = $site->environments->fetch()->get('dev');
                $env->changeConnectionMode('git')->wait();
            }
        }
    }
}

