<?php
// ex: ts=4 sts=4 sw=4 et:

namespace Lockr\Commands;

use Terminus\Commands\CommandWithSSH;
use Terminus\Models\Collections\Sites;

/**
 * @command lockdown
 */
class Lockdown extends CommandWithSSH
{
    protected function callDrush($cmd, $site, $env) {
        $this->client = 'Drush';
        $this->command = 'drush';

        $this->checkCommand($cmd);

        $elements = array(
            'site' => $site,
            'env_id' => $env,
            'command' => $cmd,
            'server' => $this->getAppserverInfo(
                array('site' => $site->get('id'), 'environment' => $env)
            ),
        );

        return $this->sendCommand($elements);
    }

    protected function callWpCli($cmd, $site, $env) {
        $this->client = 'WP-CLI';
        $this->command = 'wp';

        $elements = array(
            'site' => $site,
            'env_id' => $env,
            'command' => $cmd,
            'server' => $this->getAppserverInfo(
                array('site' => $site->get('id'), 'environment' => $env)
            ),
        );

        return $this->sendCommand($elements);
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

        if ($env->info('connection_mode') != 'sftp') {
            $this->checkConnectionMode($env);
            return;
        }

        $fmwk = $site->get('framework');

        if ($fmwk === 'drupal') {
            $this->callDrush('en -y lockr', $site, 'dev');
            $this->callDrush('cc drush', $site, 'dev');
        } else {
            $this->callWpCli('plugin install lockr --activate', $site, 'dev');
        }

        $this->commit($env, 'Lockr module installed.');

        if ($fmwk === 'drupal') {
            $this->callDrush('lockdown', $site, 'dev');
        } else {
            $this->callWpCli('lockr lockdown', $site, 'dev');
        }

        $this->commit($env, 'Lockr patches applied.');

        $email = array_shift($args);

        if ($fmwk === 'drupal') {
            $cmd = "lockr-register {$email}";
            if (isset($assoc_args['password'])) {
                $cmd .= " --password={$assoc_args['password']}";
            }
            $this->callDrush($cmd, $site, 'dev');
        } else {
            $cmd = "lockr register site --email={$email}";
            if (isset($assoc_args['password'])) {
                $cmd .= " --password={$assoc_args['password']}";
            }
            $this->callWpCli($cmd, $site, 'dev');
        }
    }
}

