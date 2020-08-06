/* detail popularity, what is played, vs what is discarded at the twist */
SELECT 
    `detail_type` AS `type`, `detail`, 
    SUM(CASE WHEN (`action` = "play_detail" ) THEN count ELSE 0 END) AS  `play`,
    SUM(CASE WHEN (`action` = "twist_detail" ) THEN count ELSE 0 END) AS `twist`,
    SUM(CASE WHEN (`action` = "edit_note" ) THEN count ELSE 0 END) AS `edit`
FROM stats 
GROUP BY `detail_type`,`detail`
ORDER BY `play`  DESC;

/* summarised by detail type, can deduce acts played (subtract auto-edit notes) */
SELECT 
    `detail_type` AS `type`,
    SUM(CASE WHEN (`action` = "play_detail" ) THEN count ELSE 0 END) AS  `play`,
    SUM(CASE WHEN (`action` = "twist_detail" ) THEN count ELSE 0 END) AS `twist`,
    SUM(CASE WHEN (`action` = "edit_note" ) THEN count ELSE 0 END) AS `edit`
FROM stats 
GROUP BY `detail_type`
ORDER BY `play`  DESC