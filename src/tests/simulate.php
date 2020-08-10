<?php
require_once '../api/gawm.php';
require_once 'testing.php';
require_once 'playthrough.php';
    
$most_guilty_tokens = function ($data)
{
    $g = gawm_list_players_by_most_guilty_tokens($data);
    if ($g[0]==$data["most_innocent"])
        array_shift($g);
    return $g[0];
};
$net_tokens = function ($data)
{
    $g = gawm_list_players_by_net_tokens($data);
    if ($g[0]==$data["most_innocent"])
        array_shift($g);
    return $g[0];
};

function make_rules($override)
{
    $rules = gawm_default_rules;
    foreach($override as $key => $value)
        $rules[$key] = $value;
    return $rules;
}

function accumulate_fates(&$data, &$fates)
{
    foreach(array_keys($data["players"]) as $pk)
    {
        $fates[$data["players"][$pk]["fate"]]++;
    }
}

function accumulate_token_counts(&$data, &$tc)
{
    foreach($tc as $k => &$v)
    {
        $v += token_count($data, $k);
    }
}

try{

    $scenario[] = gawm_default_rules;
    $scenario[] = make_rules(["new_player_tokens" => [0,1,2,4]]);
    $guilt_bias = [75,50,25];
    
    $ft=[
        "got_caught" => 0,
        "gawm" => 0,
        "got_it_right" => 0,
        "got_it_wrong" => 0,
        "got_framed" => 0,
        "got_out_alive" => 0
    ];
        
    echo "guilt, innocence, ";
    foreach(array_keys($ft) as $h)
        echo $h.', ';
    echo "rules\n";
    
    foreach($guilt_bias as $bias)
    foreach($scenario as $rules)
    {
        $fates=$ft;
        $tc=['guilt' => 0,'innocence' => 0];
        
        // random pick of strategies
        $f[] = $random_accusation;
        $f[] = $most_guilty_tokens;
        $f[] = $net_tokens;
        $f[] = function($data) use ($rules) {
            $g = gawm_list_players_by_guilty_tokens_vs_innocence_scores($data, $rules);
            if ($g[0]==$data["most_innocent"])
                array_shift($g);
            return $g[0];
        };
        
        for ($i = 1; $i <= 500; $i++)
        {
            for ($c = 4; $c <= 6; $c++)
            {
                test_playthrough($c, $rules, $f[array_rand($f)], $bias);
                accumulate_fates($data, $fates);
                accumulate_token_counts($data, $tc);
            }
        }

        echo $tc['guilt'] . ', ' . $tc['innocence']. ', ';
        foreach($fates as $v)
            echo $v.', ';
        echo $tc['guilt'] . ', ' . $tc['innocence']. ', ';
        echo '"'.json_encode($rules["new_player_tokens"]).'"'."\n";

    }

}
catch (Exception $e) {
    echo("Caught ".$e."\nWith: ".json_encode($data) ."\n");
    exit(1);
}
?>
