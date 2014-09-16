<?php
/**
 * Copyright (c)2014-2014 heiglandreas
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIBILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright ©2014-2014 Andreas Heigl
 * @license   http://www.opesource.org/licenses/mit-license.php MIT-License
 * @version   0.0
 * @since     16.09.14
 * @link      https://github.com/kimai/kimai
 */

namespace Kimai;

use \Zend_Config_Ini;
/**
 * This class holds a complete configuration scheme
 *
 * @package Kimai
 */
class ConfigFactory
{
    protected $configPath = null;

    /**
     * Set the default path to search for config files in.
     *
     * @param string $path
     *
     * @return self
     */
    public function setConfigPath($path = null)
    {
        if (null === $path || ! is_dir($path)) {
            $this->configPath = realpath(__DIR__ . '/../../config');
            return $this;
        }

        $this->configPath = realpath($path);

        return $this;
    }

    public function __construct($path = null)
    {
        $this->setConfigPath($path);
    }

    /**
     * Get a configuration object for a given name.
     *
     * This method searches in a given folder for a file named ```$config.ini```
     *
     * @param string $config
     *
     * @throws \InvalidArgumentException
     * @return \Zend_Config
     */
    public function getConfigFile($config)
    {
        $file = $this->configPath . '/' . $config . '.ini';
        if (! file_exists($file)) {
            throw new \InvalidArgumentException(sprintf(
                'The config-file "%s" could not be found in "%s"',
                $config . '.ini',
                $this->configPath
            ));
        }

        return new Zend_Config_Ini($file);
    }

    /**
     * Static way to get a configuration
     *
     * @param string $config
     * @param string $path
     *
     * @return \Zend_Config
     */
    public static function getConfig($config, $path = null)
    {
        $factory = new self($path);
        return $factory->getConfigFile($config);
    }
} 