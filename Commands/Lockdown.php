<?php
// ex: ts=4 sts=4 sw=4 et:

namespace Lockr\Commands;

use Terminus\Commands\CommandWithSSH;
use Terminus\Commands\SiteCommand;
use Terminus\Collections\Sites;

/**
 * Allows for sites to register and communicate with Lockr
 *
 * 
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
     *
     * 
     */
    public function lockdown($args, $assoc_args)
    {
        $this->ensureLogin();
        $sites = new Sites();
        $site = $sites->get(
            $this->input()->siteName(['args' => $assoc_args])
        );
        $env = $site->environments->get('dev');
				$git_mode = false;
				
        if ($env->info('connection_mode') != 'sftp') {
	        $git_mode = true;
	        $connection_args = array('mode' => 'sftp');
	        $connection_mode = $env->setConnectionMode(true, $connection_args);
	        if(!$connection_mode){
		        $this->log()->warning(
	            "The site had an issue in changing from Git mode. Please check that the code repository is
	            up to date and try again."
	          );
						return;
	        }
        } else {
	        //Ensure we don't have any changed files we'll commit over top our commits.
	        $diff = $env->diffstat();
        	$count = count((array)$diff);
        	if ($count !== 0){
	        	$this->log()->warning(
	            "Note: This site has changes to files that are not yet committed. If you want to install Lockr, 
	            you will need to commit these changes first."
	          );
						return;
        	}
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
        
        if ($git_mode){
	        $diff = $env->diffstat();
        	$count = count((array)$diff);
        	if ($count === 0){
	        	$connection_args = array('mode' => 'git');
						$connection_mode = $env->setConnectionMode(true, $connection_args);
        	}
        }
    }
}

