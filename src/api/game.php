<?php
require_once 'api.php';
require_once 'db.php';
require_once 'hashids.php';

// Takes raw data from the request
if (php_sapi_name() == "cli") {
    $json = file_get_contents('php://stdin');
} else {
    $json = file_get_contents('php://input');
}

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

$hashids = new Hashids\Hashids(GAWM_DB_PWD);

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
        $game_id = current( $hashids->decode( $request['game_id'] ) );
        // Load the game
        $data = null;
        $link = load_for_edit($game_id, $data, $request['action']);
        // Check if a game was loaded
        if (!$data) {
            http_response_code(400);
            die("Game not found: ". json_encode($game_id));
        }
        // Set param to loaded data
        $parameter_list[] = &$data;
    } else if ($parameter_name == 'game_id') {
        $parameter_list[] = current( $hashids->decode( $request['game_id'] ) );
    } else if ($parameter_name == 'hashids') {
        $parameter_list[] = $hashids;
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

        // check the endpoint was one that deals with a detail
        $a = count( array_filter( $reflection->getParameters(), function($p) {return $p->name=="detail";} ) );
        $b = count( array_filter( $reflection->getParameters(), function($p) {return $p->name=="detail_type";} ) );        
        if ($a==1 && $b==1) {
            record_event($link, $request['action'], $request['detail_type'], $request['detail']);
        }
        
        complete_edit($link, $game_id, $data);        
    }
} catch (Exception $e) {
    if (isset($link)) {
        cancel_edit($link);
    }
    http_response_code(400);
    echo 'Caught exception: ',  $e->getMessage(), "\n";
    return;
}

// convert output to json
$encoded = json_encode($output);

header('Content-Type: application/json');
echo $encoded;
?>
