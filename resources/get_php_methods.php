<?php
// generate_completions.php

$project_root = dirname(__DIR__, 3);
require_once $project_root . '/resources/classes/auto_loader.php';
$autoload = new auto_loader(true);

// find and include the global functions
include_once $project_root . '/resources/functions.php';

// Prepare result array with two keys.
$result = [
    'classes'   => [],
    'functions' => []
];

// Get the list of classes and interfaces.
$classes_to_scan = $autoload->get_class_list();
$interfaces = array_keys($autoload->get_interfaces());

// Scan each class.
foreach ($classes_to_scan as $class => $path) {

    // Skip interfaces and ensure the class actually exists.
    if (in_array($class, $interfaces) || !class_exists($class)) {
        continue;
    }

    $ref = new ReflectionClass($class);

    // Skip internal PHP classes.
    if ($ref->isInternal()) {
        continue;
    }

    $methods = [];
    // Scan only public methods.
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

        // Skip constructors.
        if ($method->getName() === '__construct') {
            continue;
        }

        // Build a string for all method parameters.
        $params = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->hasType() ? $param->getType() . " " : "";
            $default = "";
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                $default = " = " . var_export($param->getDefaultValue(), true);
            }
            $params[] = $type . '$' . $param->getName() . $default;
        }

		// Get the doc comment and clean it up
		$doc = $method->getDocComment();
		if ($doc !== false) {
			// Remove the /** and */ markers
			$doc = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $doc));
			// Remove the leading asterisks and any whitespace that follows on each line
			$doc = preg_replace('/^\s*\*\s?/m', '', $doc);
			// Remove any additional indentation (leading white space) on each line
			$doc = preg_replace('/^\s+/m', '', $doc);
		} else {
			$doc = "No documentation found";
		}

        // Get the return type string.
        $return_type = "";
        if ($method->hasReturnType()) {
            $rt = $method->getReturnType();
            $return_type = $rt->getName();
            if ($rt->allowsNull()) {
                $return_type = '?' . $return_type;
            }
        }

        $methods[] = [
            "name"   => $method->getName(),
            "params" => "(" . implode(", ", $params) . ")",
            "doc"    => "\n" . $doc,
            "static" => $method->isStatic(),
            "meta"   => $return_type
        ];
    }

    // Only add the class if it has public methods.
    if (!empty($methods)) {
        $result['classes'][$class] = $methods;
    }
}

// Add global (user-defined) functions.
$globalFunctions = get_defined_functions()['user'];
foreach ($globalFunctions as $funcName) {
    $reflection = new ReflectionFunction($funcName);

    $params = [];
    foreach ($reflection->getParameters() as $parameter) {
        $type = $parameter->hasType() ? $parameter->getType() . " " : "";
        $default = "";
        if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
            $default = " = " . var_export($parameter->getDefaultValue(), true);
        }
        $params[] = $type . '$' . $parameter->getName() . $default;
    }

    $returnType = $reflection->getReturnType();
    $returnTypeName = $returnType ? $returnType->getName() : '';

	// Get the doc comment and clean it up
	$doc = $reflection->getDocComment();
	if ($doc !== false) {
		// Remove the /** and */ markers
		$doc = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $doc));
		// Remove the leading asterisks and any whitespace that follows on each line
		$doc = preg_replace('/^\s*\*\s?/m', '', $doc);
		// Remove any additional indentation (leading white space) on each line
		$doc = preg_replace('/^\s+/m', '', $doc);
	} else {
		$doc = "No documentation found";
	}

    $result['functions'][] = [
         "name"   => $reflection->getName(),
         "params" => "(" . implode(", ", $params) . ")",
         "doc"    => "\n" . $doc,
         "static" => false, // Global functions are not static.
         "meta"   => $returnTypeName
    ];
}

header('Content-Type: application/json');
echo json_encode($result);
exit();
