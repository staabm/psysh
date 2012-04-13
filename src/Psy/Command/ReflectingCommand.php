<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Exception\RuntimeException;
use Psy\Formatter\DocblockFormatter;
use Psy\Shell;
use Psy\ShellAware;
use Psy\Util\Docblock;
use Psy\Util\Documentor;
use Psy\Util\Inspector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An abstract command with helpers for inspecting the current shell scope.
 */
abstract class ReflectingCommand extends Command implements ShellAware
{
    const CLASS_OR_FUNC     = '/^[\\\\\w]+$/';
    const INSTANCE          = '/^\$(\w+)$/';
    const CLASS_MEMBER      = '/^([\\\\\w]+)::(\w+)$/';
    const CLASS_STATIC      = '/^([\\\\\w]+)::\$(\w+)$/';
    const INSTANCE_MEMBER   = '/^\$(\w+)(::|->)(\w+)$/';
    const INSTANCE_STATIC   = '/^\$(\w+)::\$(\w+)$/';

    /**
     * Shell instance (for ShellAware interface)
     *
     * @type Psy\Shell
     */
    private $shell;

    /**
     * ShellAware interface.
     *
     * @param Psy\Shell $shell
     */
    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }

    protected function getInstance($valueName)
    {
        $valueName = trim($valueName);
        $matches   = array();
        switch (true) {
            case preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return Inspector::getReflectionClass($matches[0]);
            case preg_match(self::INSTANCE, $valueName, $matches):
                $value = $this->resolveInstance($matches[1]);
                if (is_object($value)) {
                    return Inspector::getReflectionClass($value);
                } else {
                    return $value;
                }
            default:
                throw new \InvalidArgumentException('Unknown target: '.$valueName);
        }
    }

    /**
     * Get the target for a value.
     *
     * @throws \InvalidArgumentException when the value specified can't be resolved.
     *
     * @param string $valueName Function, class, variable, constant, method or property name.
     *
     * @return array (class or instance name, member name, kind)
     */
    protected function getTarget($valueName)
    {
        $valueName = trim($valueName);
        $matches   = array();
        switch (true) {
            case preg_match(self::CLASS_OR_FUNC, $valueName, $matches):
                return array($matches[0], null, 0);

            case preg_match(self::INSTANCE, $valueName, $matches):
                return array($this->resolveInstance($matches[1]), null, 0);

            case preg_match(self::CLASS_MEMBER, $valueName, $matches):
                return array($matches[1], $matches[2], Documentor::CONSTANT | Documentor::METHOD);

            case preg_match(self::CLASS_STATIC, $valueName, $matches):
                return array($matches[1], $matches[2], Documentor::STATIC_PROPERTY | Documentor::PROPERTY);

            case preg_match(self::INSTANCE_MEMBER, $valueName, $matches):
                if ($matches[2] == '->') {
                    $kind = Documentor::METHOD | Documentor::PROPERTY;
                } else {
                    $kind = Documentor::CONSTANT | Documentor::METHOD;
                }

                return array($this->resolveInstance($matches[1]), $matches[3], $kind);

            case preg_match(self::INSTANCE_STATIC, $valueName, $matches):
                return array($this->resolveInstance($matches[1]), $matches[2], Inspector::STATIC_PROPERTY);

            default:
                throw new RuntimeException('Unknown target: '.$valueName);
        }
    }

    /**
     * Return a variable instance from the current scope.
     *
     * @throws \InvalidArgumentException when the requested variable does not exist in the current scope.
     *
     * @param string $value
     *
     * @return mixed Variable instance.
     */
    protected function resolveInstance($name)
    {
        $value = $this->getScopeVariable($name);
        if (!is_object($value)) {
            throw new RuntimeException('Unable to inspect a non-object');
        }

        return $value;
    }

    protected function getScopeVariable($name)
    {
        return $this->shell->getScopeVariable($name);
    }

    protected function getScopeVariables()
    {
        return $this->shell->getScopeVariables();
    }
}