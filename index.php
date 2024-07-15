<?php

declare(strict_types=1);

require_once './vendor/autoload.php';

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;

const API_NAMESPACE = 'Api';
const MODEL_NAMESPACE = 'Model\Api';

$content = json_decode(file_get_contents('php://input'));

$archiveName = createTarArchive(
    $_GET['archive_name'] ?? 'archive',
    getFiles($content, $_GET['interface_name'] ?? 'Json')
);

download("{$archiveName}.gz");

function createGetSet(string $property, $value, $type = null, $class = true) {
    $type ??= get_debug_type($value);

    $methodName = str_replace('_', '', mb_convert_case($property, \MB_CASE_TITLE));
    $parameter = lcfirst($methodName);

    $get = new Method('get' . $methodName);
    $get->setComment("@return {$type}");

    $set = new Method('set' . $methodName);
    $set->addParameter($parameter)->setType($type);
    $set->setComment("@param {$type} \${$parameter}\n@return \$this");

    if ($class) {
        $const = mb_strtoupper($property);
        $get->setBody("return \$this->_getData(self::DATA_{$const});");
        $set->setBody("return \$this->setData(self::DATA_{$const}, \${$parameter});");
    }

    return [$get, $set];
}

function getFiles($json, string $name) {
    $files = [];

    $class = new ClassType($className = ucfirst($name));
    $interface = new InterfaceType($interfaceName = "{$className}Interface");

    $module = str_replace('/', '\\', $_GET['namespace'] ?? 'Vendor\Module');

    $apiNamespace = new PhpNamespace($module . '\\' . API_NAMESPACE);
    $apiNamespace->add($interface);

    $modelNamespace = new PhpNamespace($module . '\\' . MODEL_NAMESPACE);
    $modelNamespace->addUse('Magento\Framework\DataObject');
    $modelNamespace->addUse($apiNamespace->resolveName($interfaceName));
    $modelNamespace->add($class);

    foreach ($json as $key => $value) {
        $type = null;
        if (\is_object($value)) {
            $files += getFiles($value, $key);
            $type = $apiNamespace->resolveName(ucfirst($key) . 'Interface');
            $modelNamespace->addUse($type);
        }

        [$get, $set] = createGetSet($key, $value, $type);
        $class->addMember($get);
        $class->addMember($set);

        [$get, $set] = createGetSet($key, $value, $type, class: false);
        $interface->addMember($get);
        $interface->addMember($set);
        $interface->addConstant('DATA_' . mb_strtoupper($key), $key);
    }

    $class->setExtends('Magento\Framework\DataObject');
    $class->addImplement($apiNamespace->resolveName($interfaceName));

    return $files + [
        to_dir($modelNamespace->resolveName($className))   => $modelNamespace,
        to_dir($apiNamespace->resolveName($interfaceName)) => $apiNamespace,
    ];
}

function to_dir(string $file) {
    return str_replace('\\', \DIRECTORY_SEPARATOR, $file) . '.php';
}

function download($name) : void {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($name));

    readfile($name);

    unlink($name);
}

function createTarArchive($name, $files) {
    if (!class_exists('PharData')) {
        return 'The Phar extension is not enabled.';
    }

    $phar = new PharData($tarFile = "/tmp/{$name}.tar");

    foreach ($files as $path => $content) {
        $phar->addFromString($path, "<?php \n\n{$content}");
    }

    $phar->compress(Phar::GZ);

    unlink($tarFile);

    return $tarFile;
}
