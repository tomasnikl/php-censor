<?php
/**
 * PHPCensor - Continuous Integration for PHP
 */

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Plugin;

/**
 * Integrates PHPCensor with Mage: https://github.com/andres-montanez/Magallanes
 *
 * @package      PHPCensor
 * @subpackage   Plugins
 */
class Mage extends Plugin
{
    protected $mageEnv;

    /**
     * {@inheritdoc}
     */
    public static function pluginName()
    {
        return 'mage';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $config = $builder->getSystemConfig('mage');
        /** @deprecated Section "mage" in the global application config is deprecated and will be deleted in version 2.0. Use the plugin option "binary_path" and "binary_name" instead. */
        if (!empty($config['bin'])) {
            $this->builder->logWarning(
                '[DEPRECATED] Section "mage" in the global application config is deprecated and will be deleted in version 2.0. Use the plugin option "binary_path" and "binary_name" instead.'
            );

            $this->executable = $config['bin'];
        /** @deprecated Option "bin" is deprecated and will be deleted in version 2.0. Use the option "binary_path" and "binary_name" instead. */
        } elseif (isset($options['bin'])) {
            $this->builder->logWarning(
                '[DEPRECATED] Option "bin" is deprecated and will be deleted in version 2.0. Use the option "binary_path" and "binary_name" instead.'
            );

            $this->executable = $builder->interpolate($options['bin']);
        } else {
            $this->executable = $this->builder->findBinary(['mage', 'mage.phar']);
        }

        if (isset($options['env'])) {
            $this->mageEnv = $builder->interpolate($options['env']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (empty($this->mageEnv)) {
            $this->builder->logFailure('You must specify environment.');
            return false;
        }

        $result = $this->builder->executeCommand($this->executable . ' deploy to:' . $this->mageEnv);

        try {
            $this->builder->log('########## MAGE LOG BEGIN ##########');
            $this->builder->log($this->getMageLog());
            $this->builder->log('########## MAGE LOG END ##########');
        } catch (Exception $e) {
            $this->builder->logFailure($e->getMessage());
        }

        return $result;
    }

    /**
     * Get mage log lines
     * @return array
     * @throws Exception
     */
    protected function getMageLog()
    {
        $logsDir = $this->build->getBuildPath() . '/.mage/logs';
        if (!is_dir($logsDir)) {
            throw new Exception('Log directory not found');
        }

        $list = scandir($logsDir);
        if ($list === false) {
            throw new Exception('Log dir read fail');
        }

        $list = array_filter($list, function ($name) {
            return preg_match('/^log-\d+-\d+\.log$/', $name);
        });
        if (empty($list)) {
            throw new Exception('Log dir filter fail');
        }

        $res = sort($list);
        if ($res === false) {
            throw new Exception('Logs sort fail');
        }

        $lastLogFile = end($list);
        if ($lastLogFile === false) {
            throw new Exception('Get last Log name fail');
        }

        $logContent = file_get_contents($logsDir . '/' . $lastLogFile);
        if ($logContent === false) {
            throw new Exception('Get last Log content fail');
        }

        $lines = explode("\n", $logContent);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);

        return $lines;
    }
}
