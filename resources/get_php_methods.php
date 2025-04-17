<?php

$project_root = dirname(__DIR__, 3);
require_once $project_root . '/resources/classes/auto_loader.php';

// We use our own auto loader and specify not to use cache
$autoload = new auto_loader(true);

include_once $project_root . '/resources/functions.php';

$result = [
    "classes"     => [],
    "functions"   => [],
    "superglobals"=> []
];

// Get the list of classes and interfaces.
$classes_to_scan = $autoload->get_class_list();
$interfaces = array_keys($autoload->get_interfaces());

// Loop over each class (skipping interfaces and internal classes).
foreach ($classes_to_scan as $class => $path) {
    if (in_array($class, $interfaces)) {
        continue;
    }
	// classes can be removed during development
    if (!class_exists($class)) {
        continue;
    }

    $ref = new ReflectionClass($class);
    if ($ref->isInternal()) {
        continue;
    }

    $methods = [];
    // Retrieve public methods.
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getName() === '__construct') {
            continue;
        }

        $params = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->hasType() ? $param->getType() . " " : "";
            $default = "";
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                $default = " = " . var_export($param->getDefaultValue(), true);
            }
            $params[] = $type . '$' . $param->getName() . $default;
        }

        // Clean up the PHPDoc block.
        $doc = $method->getDocComment();
        if ($doc !== false) {
            // Remove the /** and */ markers.
            $doc = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $doc));
            // Remove leading asterisks and any extra indentation.
            $doc = preg_replace('/^\s*\*\s?/m', '', $doc);
            $doc = preg_replace('/^\s+/m', '', $doc);
        } else {
            $doc = "";
        }

        $return_type = "";
        if ($method->hasReturnType()) {
            $rt = $method->getReturnType();
            $return_type = $rt->getName();
            if ($rt->allowsNull()) {
                $return_type = '?' . $return_type;
            }
        }

        // Build a method completion entry.
        // Use "::" for static methods and "->" for instance methods.
        $displayName = $class . ($method->isStatic() ? "::" : "->") . $method->getName();
        $methods[] = [
            "name"    => $method->getName(),
            "params"  => "(" . implode(", ", $params) . ")",
            "doc"     => $doc,
            "static"  => $method->isStatic(),
            "meta"    => $return_type
        ];
    }

    // Scan for declared properties.
    $properties = [];
    // You may filter here to include only public properties if desired.
    foreach ($ref->getProperties() as $property) {
        if (!$property->isPublic() && !$property->isProtected() && !$property->isPrivate()){
            continue;
        }

        // Build the display name: use "::$" for static properties and "->" for instance ones.
        if ($property->isStatic()) {
            $displayName = $class . "::$" . $property->getName();
        } else {
            $displayName = $class . "->" . $property->getName();
        }

        // Get the property PHPDoc if it exists.
        $propDoc = $property->getDocComment();
        if ($propDoc !== false) {
            $propDoc = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $propDoc));
            $propDoc = preg_replace('/^\s*\*\s?/m', '', $propDoc);
            $propDoc = preg_replace('/^\s+/m', '', $propDoc);
        } else {
            $propDoc = "";
        }

        // Determine the visibility.
        if ($property->isPublic()) {
            $visibility = "public";
        } elseif ($property->isProtected()) {
            $visibility = "protected";
        } elseif ($property->isPrivate()) {
            $visibility = "private";
        } else {
            $visibility = "";
        }
        if ($property->isStatic()) {
            $visibility .= " static";
        }

        $properties[] = [
            "name"    => $property->getName(),
            "display" => $displayName,
            "doc"     => $propDoc,
            "meta"    => trim($visibility)
        ];
    }

    // Only add the class if there is at least one method or property.
    if (!empty($methods) || !empty($properties)) {
        $result["classes"][$class] = [
            "methods"    => $methods,
            "properties" => $properties
        ];
    }
}

// Process global (user-defined) functions.
$userFunctions = get_defined_functions()['user'];
$functions = [];
foreach ($userFunctions as $funcName) {
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

    $docComment = $reflection->getDocComment() ?: '';
    // Clean up the function's PHPDoc block.
    $docComment = trim(preg_replace('/(^\/\*\*|\*\/$)/', '', $docComment));
    $docComment = preg_replace('/^\s*\*\s?/m', '', $docComment);
    $docComment = preg_replace('/^\s+/m', '', $docComment);

    $returnType = $reflection->getReturnType();
    $returnTypeName = $returnType ? $returnType->getName() : '';

    $functions[] = [
         "name"   => $reflection->getName(),
         "params" => "(" . implode(", ", $params) . ")",
         "doc"    => $docComment,
         "static" => false,
         "meta"   => $returnTypeName
    ];
}
$result["functions"] = $functions;

// Add PHP superglobals.
$superglobals = [
    [
         "name"    => '$_GET',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains variables passed via URL query parameters.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_POST',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains variables passed via HTTP POST.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_COOKIE',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains cookie data.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_SERVER',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains server and execution environment information.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_FILES',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains information about files uploaded via HTTP POST.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_REQUEST',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains data from GET, POST, and COOKIE.",
         "static"  => false,
         "meta"    => "superglobal"
    ],
    [
         "name"    => '$_ENV',
         "params"  => "",
         "doc"     => "PHP Superglobal: Contains environment variables.",
         "static"  => false,
         "meta"    => "superglobal"
    ]
];
$result["superglobals"] = $superglobals;

header('Content-Type: application/json');
echo json_encode($result);
exit();
