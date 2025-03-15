<?php

$project_root = dirname(__DIR__, 3);

require_once  $project_root . '/resources/classes/auto_loader.php';
global $autoload;
$autoload = new auto_loader();

$class_methods = [];
$classes_to_scan = $autoload->get_class_list();
$interfaces = array_keys($autoload->get_interfaces());

foreach ($classes_to_scan as $class => $path) {
	// Skip interfaces
	if (in_array($class, $interfaces)) {
		continue;
	}

	// Skip internal classes
	$ref = new ReflectionClass($class);
	if ($ref->isInternal()) {
		continue;
	}

	$methods = [];
	foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
		// Skip __construct
		if ($method->getName() === '__construct') {
			continue;
		}

		// Get method parameters
		$params = [];
		foreach ($method->getParameters() as $param) {
			$type = $param->hasType() ? $param->getType() . " " : "";
			$default = "";
			if ($param->isOptional() && $param->isDefaultValueAvailable()) {
				$default = " = " . var_export($param->getDefaultValue(), true);
			}
			$params[] = $type . "$" . $param->getName() . $default;
		}

		// Get the doc comment and clean it up
		$doc = $method->getDocComment();
		if ($doc !== false) {
			$doc = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $doc));
			$doc = preg_replace('/^\s*\*\s?/m', '', $doc);
		} else {
			$doc = "";
		}

		// Get the return type, if any
		$return_type = "";
		if ($method->hasReturnType()) {
			$rt = $method->getReturnType();
			$return_type = $rt->getName();
			if ($rt->allowsNull()) {
				$return_type = "?" . $return_type;
			}
		}

		$methods[] = [
			"name"    => $method->getName(),
			"params"  => "(" . implode(", ", $params) . ")",
			"doc"     => $doc,
			"static"  => $method->isStatic(),
			"return"  => $return_type
		];
	}
	$class_methods[$class] = $methods;
}

header('Content-Type: application/json');
echo json_encode($class_methods);
