<?php

/**
 * AppserverIo\Meta\Composer\Script\Setup
 *
 * PHP version 5
 *
 * @category   Appserver
 * @subpackage Composer
 * @package    TechDivision_ApplicationServer
 * @author     Tim Wagner <tw@techdivision.com>
 * @copyright  2014 TechDivision GmbH <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link       http://www.appserver.io
 */

namespace AppserverIo\Meta\Composer\Script;

use Composer\Script\Event;

/**
 * Class that provides functionality that'll be executed by composer
 * after installation or update of the application server.
 *
 * @category Appserver
 * @subpackage Composer
 * @package TechDivision_ApplicationServer
 * @author Tim Wagner <tw@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link http://www.appserver.io
 */
class Setup
{

    /**
     * OS signature when calling php_uname('s') on Mac OS x 10.8.x/10.9.x.
     *
     * @var string
     */
    const DARWIN = 'darwin';

    /**
     * OS signature when calling php_uname('s') on Linux Debian/Ubuntu/Fedora and CentOS.
     *
     * @var string
     */
    const LINUX = 'linux';

    /**
     * OS signature when calling php_uname('s') on Windows.
     *
     * @var string
     */
    const WINDOWS = 'windows';

    /**
     * The array with the merged and os specific template variables.
     *
     * @var array
     */
    protected static $mergedProperties = array();

    /**
     * The available properties we used for parsing the template.
     *
     * @var array
     */
    protected static $defaultProperties = array(
        'appserver.php.version' => PHP_VERSION,
        'appserver.version' => '1.0.0-alpha',
        'appserver.admin.email' => 'info@appserver.io',
        'container.server.worker.acceptMin' => 3,
        'container.server.worker.acceptMax' => 8,
        'container.http.worker.number' => 64,
        'container.http.host' => '127.0.0.1',
        'container.http.port' => 9080,
        'container.https.worker.number' => 64,
        'container.https.host' => '127.0.0.1',
        'container.https.port' => 9443,
        'container.persistence-container.worker.number' => 64,
        'container.persistence-container.host' => '127.0.0.1',
        'container.persistence-container.port' => 8585,
        'container.memcached.worker.number' => 8,
        'container.memcached.host' => '127.0.0.1',
        'container.memcached.port' => 11210,
        'container.message-queue.worker.number' => 8,
        'container.message-queue.host' => '127.0.0.1',
        'container.message-queue.port' => 8587,
        'appserver.web-socket.host' => '127.0.0.1',
        'appserver.web-socket.port' => 8589,
        'php-fpm.port' => 9100,
        'php-fpm.host' => '127.0.0.1',
        'appserver.umask' => '0002',
        'appserver.user' => 'nobody',
        'appserver.group' => 'nobody'
    );

    /**
     * The OS specific configuration properties.
     *
     * @var array
     */
    protected static $osProperties = array(
        'darwin'  => array('os.family' => Setup::DARWIN, 'appserver.group' => 'staff'),
        'debian'  => array('os.family' => Setup::LINUX,  'appserver.group' => 'www-data', 'appserver.user' => 'www-data'),
        'ubuntu'  => array('os.family' => Setup::LINUX,  'appserver.group' => 'www-data', 'appserver.user' => 'www-data'),
        'fedora'  => array('os.family' => Setup::LINUX),
        'redhat'  => array('os.family' => Setup::LINUX),
        'centOS'  => array('os.family' => Setup::LINUX),
        'windows' => array('os.family' => Setup::WINDOWS)
    );

    /**
     * Returns the Linux distribution we're running on.
     *
     * @return string The Linux distribution we're running on
     */
    public static function getLinuxDistro()
    {

        // declare Linux distros(extensible list).
        $distros = array(
            "arch" => "arch-release",
            "debian" => "debian_version",
            "fedora" => "fedora-release",
            "ubuntu" => "lsb-release",
            'redhat' => 'redhat-release',
            'centOS' => 'centos-release'
        );

        // get everything from /etc directory.
        $etcList = scandir('/etc');

        // loop through /etc results...
        $distro = '';

        foreach ($etcList as $entry) { // iterate over all found files

            // loop through list of distros..
            foreach ($distros as $distroReleaseFile) {

                // match was found.
                if ($distroReleaseFile === $entry) {

                    // find distros array key (i.e. distro name) by value (i.e. distro release file)
                    $distro = array_search($distroReleaseFile, $distros);
                    break 2; // break inner and outer loop.
                }
            }
        }

        // return the found distro string
        return $distro;
    }

    /**
     * Merge the properties based on the passed OS.
     *
     * @param string $os The OS we want to merge the properties for
     *
     * @return void
     */
    public static function prepareProperties($os)
    {
        Setup::$mergedProperties = array_merge(
            array('install.dir' => getcwd()),
            Setup::$defaultProperties,
            Setup::$osProperties[$os]
        );
    }

    /**
     * This method will be invoked by composer after a successfull installation and creates
     * the application server configuration file under etc/appserver/appserver.xml.
     *
     * @param \Composer\Script\Event $event The event that invokes this method
     *
     * @return void
     */
    public static function postInstall(Event $event)
    {

        // load the OS signature
        $os = strtolower(php_uname('s'));

        // check what OS we are running on
        switch ($os) {

            // installation running on Linux
            case Setup::LINUX:

                // get the distribution
                $distribution = Setup::getLinuxDistribution();
                if ($distribution == null) { // if we cant find one of the supported systems

                    // set debian as default
                    $distribution = 'debian';

                    // write a message to the console
                    $event->getIo()->write(
                        sprintf(
                            '<warning>Unknown Linux distribution found, use Debian default values: ' .
                            'Please check user/group configuration in etc/appserver/appserver.xml</warning>'
                        )
                    );
                }

                // merge the properties for the found Linux distribution
                Setup::prepareProperties($distribution);

                // process the binaries for the systemd services on Fedora
                if ($distribution === 'fedora' || $distribution === 'redhat') {
                    Setup::processTemplate('bin/appserver', 0775);
                    Setup::processTemplate('bin/appserver-watcher', 0775);
                }
                break;

            // installation running on Mac OS X
            case Setup::DARWIN:

                // merge the properties for Mac OS X
                Setup::prepareProperties($os);

                // process the control files for the launchctl service
                Setup::copyOsSpecificResource(Setup::DARWIN, 'sbin/appserverctl', 0775);
                Setup::copyOsSpecificResource(Setup::DARWIN, 'sbin/appserver-watcherctl', 0775);
                Setup::copyOsSpecificResource(Setup::DARWIN, 'sbin/appserver-php5-fpmctl', 0775);

                // process the binaries for the launchctl service
                Setup::processTemplate('bin/appserver');
                Setup::processTemplate('bin/appserver-watcher');
                break;

            // installation running on Windows
            case Setup::WINDOWS:

                // merge the properties for Windows
                Setup::prepareProperties($os);

                // process the control files for the launchctl service
                Setup::copyOsSpecificResource(Setup::WINDOWS, 'appserver.bat');
                Setup::copyOsSpecificResource(Setup::WINDOWS, 'appserver-php5-fpm.bat');
                break;

            // all other OS are NOT supported actually
            default:

                break;
        }

        // process and move the configuration files their target directory
        Setup::processTemplate('var/tmp/opcache-blacklist.txt');
        Setup::processTemplate('etc/appserver/appserver.xml');
    }

    /**
     * Returns the configuration value with the passed key.
     *
     * @return mixed|null The configuration value
     */
    public static function getValue($key)
    {
        if (array_key_exists($key, Setup::$mergedProperties)) {
            return Setup::$mergedProperties[$key];
        }
    }

    /**
     * Copies the passed OS specific resource file to the target directory.
     *
     * @param string  $os       The OS we want to copy the files for
     * @param string  $resource The resource file we want to copy
     * @param integer $mode     The mode of the target file
     *
     * @return void
     */
    public static function copyOsSpecificResource($os, $resource, $mode = 0664)
    {

        // we need the installation directory
        $installDir = Setup::getValue('install.dir');

        // prepare source and target directory
        $source = sprintf('%s/resources/os-specific/%s/%s', $installDir, $os, $resource);
        $target = sprintf('%s/%s', $installDir, $resource);

        // prepare the target directory
        Setup::prepareDirectory($target);

        // copy the file to the target directory
        copy($source, $target);

        // set the correct mode for the file
        Setup::changeFilePermissions($target, $mode);
    }

    /**
     * Processes the template and replace the properties with the OS specific values.
     *
     * @param string $template The path to the template
     * @param integer $mode    The mode of the target file
     *
     * @return void
     */
    public static function processTemplate($template, $mode = 0664)
    {

        // prepare the target directory
        Setup::prepareDirectory($template);

        // process the template and store the result in the passed file
        ob_start();
        include sprintf('resources/templates/%s.phtml', $template);
        file_put_contents($template, ob_get_clean());

        // set the correct mode for the file
        Setup::changeFilePermissions($template, $mode);
    }

    /**
     * Sets the passed mode for the file if NOT on Windows.
     *
     * @param string  $filename The filename to set the mode for
     * @param integer $mode     The mode to set
     *
     * @return void
     */
    public static function changeFilePermissions($filename, $mode = 0644)
    {
        if (Setup::WINDOWS !== strtolower(php_uname('s'))) {
            chmod($filename, $mode);
        }
    }

    /**
     * Prepares the passed directory if necessary.
     *
     * @param string  $directory The directory to prepare
     * @param integer $mode      The mode of the directory
     *
     * @return void
     */
    public static function prepareDirectory($directory, $mode = 0775)
    {
        if (is_dir(dirname($directory)) === false) {
            mkdir(dirname($directory), $mode, true);
        }
    }
}
