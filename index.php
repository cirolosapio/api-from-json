<?php

require_once './vendor/autoload.php';

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\InterfaceType;

$content = json_decode(file_get_contents('php://input'));

$archiveName = createTarArchive('archive', getFiles($content));
download("$archiveName.gz");

function createGetSet($property, $value, $type = null, $class = true)
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

function getFiles($json, $name = 'Start')
{
    $files = [];

    $className = ucfirst($name);
    $class = new ClassType($className);

    $interfaceName = ucfirst($name).'Interface';
    $interface = new InterfaceType($interfaceName);

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

    $class->setExtends('\Magento\Framework\DataObject');
    $class->addImplement($interfaceName);

    $files = array_merge($files, [$className => $class, $interfaceName => $interface]);

    return $files;
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

    $phar = new PharData($tarFile = "{$name}.tar");

    foreach ($files as $file => $content) {
        $phar->addFromString($file, "<?php \n\n". $content);
    }

    $phar->compress(Phar::GZ);
    unlink($tarFile);

    return $tarFile;
}
