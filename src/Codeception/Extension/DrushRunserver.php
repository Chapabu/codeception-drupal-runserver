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
        if (is_null($this->config['drushBinary'])) {
            $this->drushBinary = $this->config['drushBinary'];
        }
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
        $command[] = $this->getServerHost();
        $command[] = '-r ' . $this->getDrupalRoot();

        $variables = $this->getVariables();

        if (!empty($variables)) {
            $command[] = '--variables=' . $variables;
        }

        return escapeshellcmd(implode(' ', $command));
    }

    /**
     * Get the hostname and port for the server form the provided configuration.
     *
     * @return string
     */
    private function getServerHost()
    {
        $host = [];

        if (isset($this->config['hostname']) && !is_null($this->config['hostname'])) {
            $host[] = $this->config['hostname'];
        }

        if (isset($this->config['port']) && !is_null($this->config['port'])) {
            $host[] = $this->config['port'];
        }

        return implode(':', $host);
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

        $this->writeln('Started Drush server.');
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
            $this->writeln('Stopped Drush server.');
            unset($this->resource);
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
