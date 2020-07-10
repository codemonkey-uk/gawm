<?php
require 'api/api.php';
require 'api/db.php';

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$request = json_decode($json, true);

// Get action
if (!isset($request['action'])) {
    http_response_code(400);
    die('No action requested.');
} else {
    $action_function = '_api_'.$request['action'];
}

// Auto-wiring
if (!function_exists($action_function)) {
    http_response_code(400);
    die('Action does not exist');
}
// Build parameters
$parameter_list = array();
$reflection = new ReflectionFunction($action_function);
foreach ($reflection->getParameters() as $parameter) {
    $parameter_name = $parameter->name;
    // If we have a data parameter, load the gamestate in to it
    if ($parameter_name == 'data') {
        // Check we've been given a game_id
        if (!isset($request['game_id'])) {
            http_response_code(400);
            die("game_id: parameter missing");
        }
        $game_id = $request['game_id'];
        // Load the game
        $data = null;
        $link = load_for_edit($game_id, $data);
        // Check if a game was loaded
        if (!$data) {
            http_response_code(400);
            die("Game not found.");
        }
        // Set param to loaded data
        $parameter_list[] = &$data;
    } else if (isset($request[$parameter_name])) {
        $parameter_list[] = $request[$parameter_name];
    } else {
        http_response_code(400);
        die($parameter_name.": parameter missing");
    }
}

try {
    // Run api code
    $output = call_user_func_array($action_function, $parameter_list);
    // Save data if we loaded it
    if (isset($link)) {
        complete_edit($link, $game_id, $data);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo 'Caught exception: ',  $e->getMessage(), "\n";
    return;
}

// convert output to json
$encoded = json_encode($output);

header('Content-Type: application/json');
echo $encoded;
?>