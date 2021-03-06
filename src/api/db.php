<?php

// secrets injected by build system, see: cp_api.sh & makefile
function db_connect()
{
    $hostname = GAWM_DB_HOST;   // eg. mysql.yourdomain.com (unique)
    $username = GAWM_DB_USER;   // the username specified when setting-up the database
    $password = GAWM_DB_PWD;   // the password specified when setting-up the database
    $database = "gawm";   // the database name chosen when setting-up the database (unique)
    $link = mysqli_connect($hostname,$username,$password,$database);
    if (!$link)
    {
        http_response_code(500);
        die ("Database unavailable.");
    }
    return $link;
}

function save_new_game($game)
{
    $link = rate_limited_connect("new");
    
    $query = "INSERT INTO `games` (`uid`, `time`, `data`) VALUES (NULL, CURRENT_TIMESTAMP, ?)";
    if ($stmt = mysqli_prepare($link, $query))
    {
        $game_encoded = json_encode($game);
        mysqli_stmt_bind_param($stmt, "s", $game_encoded);
        mysqli_stmt_execute($stmt);
        $game_id = mysqli_insert_id($link);
    }
    purge_old_games($link);
    mysqli_close($link);

    return $game_id;
}

// mostly making sure there is no retention of player data
function purge_old_games($link)
{
    // old game json
    $query = "DELETE FROM `gawm`.`games` WHERE time < DATE_SUB(NOW(), INTERVAL 14 day);";
    if ($stmt = mysqli_prepare($link, $query))
    {
        mysqli_stmt_execute($stmt);
    }
    // old ip-addresses from rate-limiter
    $query = "DELETE FROM `gawm`.`rates` WHERE time < DATE_SUB(NOW(), INTERVAL 14 day);";
    if ($stmt = mysqli_prepare($link, $query))
    {
        mysqli_stmt_execute($stmt);
    }    
}

function load_and_release($game_id)
{
    $link = db_connect();
    $query = "SELECT `data` FROM `games` WHERE `uid` = ?;";

    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        mysqli_stmt_bind_param($stmt, "s", $game_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result))
        {
            $data = json_decode($row['data'],true);
        }
    }
    mysqli_close($link);

    return $data;
}

function load_for_edit($game_id, &$data, $action)
{
    $link = rate_limited_connect($action);
    $query = "SELECT `data` FROM `games` WHERE `uid` = ? FOR UPDATE;";

    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        mysqli_stmt_bind_param($stmt, "s", $game_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result))
        {
            $data = json_decode($row['data'],true);
        }
    }

    return $link;
}

function complete_edit($link, $game_id, $data)
{
    $query = "UPDATE `games` SET `data` = ? WHERE `uid` = ? ";
    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        $data_encoded = json_encode($data);
        mysqli_stmt_bind_param($stmt, "si", $data_encoded, $game_id);
        mysqli_stmt_execute($stmt);
    }
    mysqli_close($link);
}

function record_event($link, $action, $detail_type, $detail)
{
    $query = "INSERT INTO stats (date, action, detail_type, detail) "
    . "VALUES (CURDATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE count = count + 1;";
    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        // - to keep player uids out of the stats table
        if ($detail_type=='player' || $action=='give_token')
            $detail = ($detail!=0) ? 1 : 0;
        
        mysqli_stmt_bind_param($stmt, "ssi", $action, $detail_type, $detail);
        mysqli_stmt_execute($stmt);
    }
}

function rate_limited_connect($action)
{
    $link = db_connect();
    
    // new games, rate limit at 12 per day, 
    // allows frequent play and mistakes on creation, but not spam
    // note edits, 30 an hour allows all notes to be set during the course of a game
    // but should prevents server being abused as IM chat room  
    // move_detail, 6 moves, everyone can move one object, then once per 5 mins
    $rates = [
        "new" => [ "n" => 0.5, "f" => "HOUR", "l" => 12],
        "edit_note" => [ "n" => 0.5, "f" => "MINUTE", "l" => 30],
        "move_detail" => [ "n" => 0.2, "f" => "MINUTE", "l" => 6]
    ];
    if (array_key_exists($action, $rates))
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($ip=="") $ip="127.0.0.1"; // for localhost testing
        
        $n = $rates[$action]["n"];
        $f = $rates[$action]["f"];
        $l = $rates[$action]["l"];
        
        $query = "INSERT INTO rates (`ipv4`, action)"
            . "VALUES (INET_ATON(?), ?) "
            . "ON DUPLICATE KEY UPDATE count = 1 + GREATEST(0, `count` - (".$n."*TIMESTAMPDIFF(".$f.",`time`,NOW())))";
        
        $stmt = mysqli_stmt_init($link);
        if (mysqli_stmt_prepare($stmt, $query))
        {
            mysqli_stmt_bind_param($stmt, "ss", $ip, $action);
            mysqli_stmt_execute($stmt);
        }
        
        $query = "SELECT `count` FROM `rates` WHERE (`ipv4` = INET_ATON(?) AND `action` = ?)";

        $stmt = mysqli_stmt_init($link);
        if (mysqli_stmt_prepare($stmt, $query))
        {
            mysqli_stmt_bind_param($stmt, "ss", $ip, $action);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) 
            {
                $count = $row['count'];
                if ($count > $l) 
                {
                    mysqli_close($link);
                    http_response_code(429);
                    $fn = array('HOUR' => 60*60,'MINUTE' => 60);
                    $d = (($count-$l)/$n);
                    header('Retry-After: '.($d * $fn[$f]), false);
                    die ("Exceded usage limit ".$count." / ".$l);
                }
            }
        }
    }
    
    return $link;
}

function cancel_edit($link)
{
    mysqli_close($link);
}

?>
