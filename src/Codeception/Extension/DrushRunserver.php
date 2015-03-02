<?php namespace Codeception\Extension;

use Codeception\Configuration;
use Codeception\Exception\Extension as ExtensionException;
use Codeception\Platform\Extension;

/**
 * Class DrushRunserver
 * @package Codeception\Extension
 */
class DrushRunserver extends Extension
{
    /**
     * @var array
     */
    static $events = [
      'suite.before' => 'beforeSuite'
    ];

    /**
     * @var resource
     */
    private $resource;


    /**
     * @var
     */
    private $pipes;

    /**
     * @var string
     *   String containing the path to the Drush binary.
     */
    private $drushBinary = 'drush';


    /**
     *  Construct a DrushRunserver instance.
     */
    public function __construct($config, $options)
    {
        // ToDo: Add in a config option to allow people to override this should they wish to try playing with PHP 5.3/CGI.
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            throw new ExtensionException($this, 'Requires PHP 5.4 or above.');
        }

        parent::__construct($config, $options);

        // Allow a configurable path to Drush in case it's not installed system-wide.
        if (isset($this->config['drushBinary']) && !is_null($this->config['drushBinary'])) {
            $this->drushBinary = $this->config['drushBinary'];
        }

        // Get the sleep timeout from the config if it's available.
        $this->sleep = isset($this->config['sleep']) ? $this->config['sleep'] : 2;

        $this->startServer();

        $resource = $this->resource;

        
        register_shutdown_function(
          function () use ($resource) {
              if (is_resource($resource)) {
                  proc_terminate($resource);
              }
          }
        );
    }

    /**
     * Get the Drupal root directory.
     *
     * @return string
     *   The root directory of the Drupal installation.
     */
    private function getDrupalRoot()
    {
        // We can't get getcwd() as a default parameter, so this will have to do.
        if (is_null($this->config['drupalRoot'])) {
            return codecept_root_dir();
        } else {
            // If a user has passed in a path to their Drupal root, then we'll still need to append the current working
            // directory to it.
            return codecept_root_dir($this->config['drupalRoot']);
        }
    }

    /**
     * Build up the Drush command to run the server with.
     */
    private function getCommand()
    {
        // ToDo: Make this more configurable.
        // ToDo: Somehow find out which Drush the user is using as the commands are different.
        $command = [];
        $command[] = $this->drushBinary;
        $command[] = 'runserver';
        $command[] = $this->getServerHost();
        $command[] = '-r ' . $this->getDrupalRoot();

        $variables = $this->getVariables();

        if (!empty($variables)) {
            $command[] = '--variables=' . $variables;
        }

        return escapeshellcmd(implode(' ', $command));
    }

    /**
     * Get the hostname and port for the server from the provided configuration.
     *
     * @return string
     *   A formatted host:port string.
     */
    private function getServerHost()
    {
        $host = [];

        $host[] = $this->getHostname();
        $host[] = $this->getPort();

        return implode(':', $host);
    }

    /**
     * Get the hostname for the server from the provided configuration.
     *
     * @return string
     *  Either the configured hostname, or 127.0.0.1.
     */
    private function getHostname() {
        if (isset($this->config['hostname']) && !is_null($this->config['hostname'])) {
            return $this->config['hostname'];
        }

        return '127.0.0.1';
    }

    /**
     * Get the port for the server from the provided configuration.
     *
     * @return string
     *  Either the configured port, or 8080.
     */
    private function getPort() {
        if (isset($this->config['port']) && !is_null($this->config['port'])) {
            return $this->config['port'];
        }

        return '8080';
    }

    /**
     * Get a string
     * @return string
     */
    private function getVariables()
    {
        $variables = [];

        if (isset($this->config['variables']) && is_array($this->config['variables'])) {
            foreach ($this->config['variables'] as $variable => $value) {
                $variables[] = $variables . '=' . $value;
            }
        }

        return implode(',', $variables);
    }

    /**
     * Get a descriptorspec ready to pass to proc_open().
     *
     * @see proc_open()
     * @see startServer()
     *
     * @return array
     *   An array that can be passed to proc_open to be used as a descriptor spec.
     */
    private function getDescriptorSpec()
    {
        return [
          ['pipe', 'r'],
          ['file', Configuration::logDir() . 'drush.runserver.output.txt', 'w'],
          ['file', Configuration::logDir() . 'drush.runserver.errors.txt', 'a']
        ];
    }

    /**
     * Start the Drush server.
     */
    private function startServer()
    {
        if ($this->resource !== null) {
            return;
        }

        $this->writeln('Starting Drush server...');

        // Get the actual command and a descriptor spec to pass to proc_open().
        $command = $this->getCommand();

        $this->writeln('<debug>Using command: ' . $command . '</debug>');
        
        $descriptorSpec = $this->getDescriptorSpec();

        // Start the process.
        $this->resource = proc_open($command, $descriptorSpec, $this->pipes, null, null, ['bypass_shell' => true]);

        // Check to see if $resource is actually a resource.
        if (!is_resource($this->resource)) {
            throw new ExtensionException($this, 'Failed to start server.');
        }

        // Check to ensure the process is actually running.
        if (!proc_get_status($this->resource)['running']) {
            proc_close($this->resource);
            throw new ExtensionException($this, 'Failed to start server.');
        }

        // This bit of code lifted from the fantastic Phantoman plugin.
        // Check it out here: https://github.com/site5/phantoman.

        // Wait until the server is reachable before continuing
        $max_checks = 10;
        $checks = 0;

        $this->write("Waiting for the Drush server to be reachable");

        while (true) {
            if ($checks >= $max_checks) {
                throw new ExtensionException($this, 'Drush server never became reachable');
                break;
            }
            if ($fp = @fsockopen($this->getHostname(), $this->getPort(), $errCode, $errStr, 10)) {
                $this->writeln('');
                $this->writeln("Drush server now accessible");
                fclose($fp);
                break;
            }
            $this->write('.');
            $checks++;

            // Wait before checking again
            sleep($this->sleep);
        }

        $this->writeln('Started Drush server.');

        sleep($this->sleep);
    }

    /**
     * Stop the Drush server.
     */
    private function stopServer()
    {
        if ($this->resource !== null) {
            $this->writeln('Stopping Drush server...');
            foreach ($this->pipes AS $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($this->resource, 2);
            unset($this->resource);

            sleep($this->sleep);
            $this->writeln('Stopped Drush server.');
        }
    }

    /**
     * Implement __clone magic method to prevent cloning of class.
     */
    public function __clone()
    {
        // Prevent cloning.
    }

    /**
     * Act on destruction of this class.
     */
    public function __destruct()
    {
        $this->stopServer();
    }

    /**
     *
     */
    public function beforeSuite()
    {
        // This needs to be here otherwise Codeception just destroys the extension as soon as it's created.
    }

}
