<?php

declare(strict_types=1);

namespace CiroLoSapio\Parser;

use const DIRECTORY_SEPARATOR;

use function is_object;

use const MB_CASE_TITLE;

use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Phar;
use PharData;

class Processor {
    public const API_NAMESPACE = 'Api';
    public const MODEL_NAMESPACE = 'Model\Api';
    public const CONST_PREFIX = 'DATA_';

    public function __construct(
        private mixed $json,
        private ?string $module = null,
        private ?string $first_name = null,
        private ?string $archive_name = null,
    ) {
        $this->module = str_replace('/', '\\', $module ?? 'Vendor\Module');
        $this->first_name = $first_name ?? 'Payload';
        $this->archive_name = $archive_name ?? 'temp';
    }

    /**
     * @throws InvalidArgumentException
     * @throws InvalidStateException
     *
     * @return array<string,PhpNamespace>
     */
    public function getFiles(mixed $json = null, string $first_name = null) : array {
        $files = [];

        $class = new ClassType($className = ucfirst($first_name ?? $this->first_name));
        $interface = new InterfaceType($interfaceName = "{$className}Interface");

        $apiNamespace = new PhpNamespace($this->module . '\\' . self::API_NAMESPACE);
        $apiNamespace->add($interface);

        $modelNamespace = new PhpNamespace($this->module . '\\' . self::MODEL_NAMESPACE);
        $modelNamespace->addUse('Magento\Framework\DataObject');
        $modelNamespace->addUse($apiNamespace->resolveName($interfaceName));
        $modelNamespace->add($class);

        foreach ($json ?? $this->json as $key => $value) {
            $type = null;
            if (is_object($value)) {
                $files += $this->getFiles($value, $key);
                $type = $apiNamespace->resolveName(ucfirst($key) . 'Interface');
                $modelNamespace->addUse($type);
            }

            [$get, $set] = $this->createGetSet($key, $value, $type);
            $class->addMember($get);
            $class->addMember($set);

            [$get, $set] = $this->createGetSet($key, $value, $type, class: false);
            $interface->addMember($get);
            $interface->addMember($set);
            $interface->addConstant(self::CONST_PREFIX . mb_strtoupper($key), $key);
        }

        $class->setExtends('Magento\Framework\DataObject');
        $class->addImplement($apiNamespace->resolveName($interfaceName));

        return $files + [
            $this->to_path($modelNamespace->resolveName($className))   => $modelNamespace,
            $this->to_path($apiNamespace->resolveName($interfaceName)) => $apiNamespace,
        ];
    }

    public function download(string $path) : void {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));

        readfile($path);

        unlink($path);
    }

    /** @param array<string,string> $files */
    public function createTarArchive(array $files) : string {
        if (!class_exists('PharData')) {
            return 'The Phar extension is not enabled.';
        }

        $phar = new PharData($tarFilePath = "/tmp/{$this->archive_name}.tar");

        foreach ($files as $path => $content) {
            $phar->addFromString($path, "<?php \n\n{$content}");
        }

        $phar->compress(Phar::GZ);

        unlink($tarFilePath);

        return $tarFilePath . '.gz';
    }

    /**
     * @throws InvalidArgumentException
     * @throws InvalidStateException
     *
     * @return Method[]
     */
    private function createGetSet(string $property, mixed $value, mixed $type = null, bool $class = true) : array {
        $type ??= get_debug_type($value);

        $methodName = str_replace('_', '', mb_convert_case($property, MB_CASE_TITLE));
        $parameter = lcfirst($methodName);

        $get = new Method("get{$methodName}");

        $set = new Method("set{$methodName}");
        $set->addParameter($parameter)->setType($type);

        if ($class) {
            $const = mb_strtoupper($property);
            $get->setBody('return $this->_getData(self::' . self::CONST_PREFIX . "{$const});");
            $set->setBody('return $this->setData(self::' . self::CONST_PREFIX . "{$const}, \${$parameter});");
        } else {
            $get->setComment("@return {$type}");
            $set->setComment("@param {$type} \${$parameter}\n@return \$this");
        }

        return [$get, $set];
    }

    private function to_path(string $file) {
        return str_replace('\\', DIRECTORY_SEPARATOR, $file) . '.php';
    }
}
