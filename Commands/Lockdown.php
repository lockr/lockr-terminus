<?php
// ex: ts=4 sts=4 sw=4 et:

namespace Lockr\Commands;

use Terminus\Commands\CommandWithSSH;
use Terminus\Commands\SiteCommand;
use Terminus\Collections\Sites;

/**
 * Allows for sites to register and communicate with Lockr
 *
 * @command lockdown
 */
class Lockdown extends CommandWithSSH
{
    protected function callCommand($cmd, $env) {
        $this->ensureCommandIsPermitted($cmd);
        $env->sendCommandViaSSH($cmd);
    }

    protected function commit($env, $msg) {
        $workflow = $env->commitChanges($msg);
        $workflow->wait();
        $this->workflowOutput($workflow);
    }

    /**
     * Install and register Lockr, and apply patches.
     *
     * ## OPTIONS
     *
     * <email>
     * : The email to register with.
     *
     * [--password=<password>]
     * : The password to match the email (if applicable).
     *
     * [--site=<site>]
     * : Site to lockdown.
     */
    public function __invoke($args, $assoc_args)
    {
        $this->ensureLogin();
        $sites = new Sites();
        $site = $sites->get(
            $this->input()->siteName(['args' => $assoc_args])
        );
        $env = $site->environments->get('dev');

        if ($env->get('connection_mode') != 'sftp') {
            $git_mode = true;
            $env->changeConnectionMode('sftp')->wait();
        } else {
            $git_mode = false;
            // Ensure we don't have any changed files we'll commit over top our commits.
            $diff = $env->diffstat();
            $count = count((array) $diff);
            if ($count !== 0){
                $this->log()->warning(
                    'Note: This site has changes to files that are not yet committed. ' .
                    'If you want to install Lockr, you will need to commit these changes first.'
                );
                return;
            }
        }

        $fmwk = $site->get('framework');

        if ($fmwk === 'drupal') {
            $this->callCommand('drush en -y lockr', $env);
            $this->callCommand('drush cc drush', $env);
        } else {
            $this->callCommand('wp plugin install lockr --activate', $env);
        }

        $this->commit($env, 'Lockr module installed.');

        if ($fmwk === 'drupal') {
            $this->callCommand('drush lockdown', $env);
        } else {
            $this->callCommand('wp lockr lockdown', $env);
        }

        $this->commit($env, 'Lockr patches applied.');

        $email = array_shift($args);

        if ($fmwk === 'drupal') {
            $cmd = "drush lockr-register {$email}";
            if (isset($assoc_args['password'])) {
                $cmd .= " --password={$assoc_args['password']}";
            }
        } else {
            $cmd = "wp lockr register site --email={$email}";
            if (isset($assoc_args['password'])) {
                $cmd .= " --password={$assoc_args['password']}";
            }
        }
        $this->callCommand($cmd, $env);

        if ($git_mode){
            $diff = $env->diffstat();
            $count = count((array) $diff);
            if ($count === 0){
                // Re-get the site to prevent "already in git mode" message
                $env = $site->environments->fetch()->get('dev');
                $env->changeConnectionMode('git')->wait();
            }
        }
    }
}

