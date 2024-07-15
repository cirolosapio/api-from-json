<?php

declare(strict_types=1);

require_once './vendor/autoload.php';

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;

$content = json_decode(file_get_contents('php://input'));

$archiveName = createTarArchive(
    $_GET['archive_name'] ?? 'archive',
    getFiles($content, $_GET['interface_name'] ?? 'Json')
);

download("$archiveName.gz");

function createGetSet(string $property, $value, $type = null, $class = true)
{
    $type ??= get_debug_type($value);

    $methodName = str_replace('_', '', mb_convert_case($property, MB_CASE_TITLE));
    $parameter = lcfirst($methodName);

    $get = new Method('get'. $methodName);
    $get->setComment("@return {$type}");

    $set = new Method('set'. $methodName);

    $set->addParameter($parameter)->setType($type);
    $set->setComment("@param {$type} \${$parameter}\n@return \$this");

    if ($class) {
        $const = strtoupper($property);
        $get->setBody("return \$this->_getData(self::DATA_$const);");
        $set->setBody("return \$this->setData(self::DATA_$const, \${$parameter});");
    }

    return [$get, $set];
}

function getFiles($json, string $name)
{
    $files = [];

    $class = new ClassType($className = ucfirst($name));
    $interface = new InterfaceType($interfaceName = "{$className}Interface");

    foreach($json as $key => $value) {
        $type = null;
        if(is_object($value)) {
            $files = array_merge($files, getFiles($value, $key));
            $type = ucfirst($key);
        }

        [$get, $set] = createGetSet($key, $value, $type);
        $class->addMember($get);
        $class->addMember($set);

        [$get, $set] = createGetSet($key, $value, $type, false);
        $interface->addMember($get);
        $interface->addMember($set);
        $interface->addConstant('DATA_'. strtoupper($key), $key);
    }

    $class->setExtends('DataObject');
    $class->addImplement($interfaceName);

    return array_merge($files, parseFile($className, $class), parseFile($interfaceName, $interface));
}

function parseFile(string $name, ClassType|InterfaceType $file)
{
    $namespace = new PhpNamespace($_GET['namespace'] ?? 'Vendor\Module');
    $namespace->addUse('Magento\Framework\DataObject');
    $namespace->add($file);

    $printer = new Printer();
    $printer->setTypeResolving(false);

    return [ $name => $printer->printNamespace($namespace) ];
}

function download($name)
{
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

function createTarArchive($name, $files)
{
    if (!class_exists('PharData')) {
        return 'The Phar extension is not enabled.';
    }

    $phar = new PharData($tarFile = "/tmp/{$name}.tar");

    foreach ($files as $file => $content) {
        $phar->addFromString($file, "<?php \n\n{$content}");
    }

    $phar->compress(Phar::GZ);
    unlink($tarFile);

    return $tarFile;
}
