DELETE FROM `gawm`.`games` WHERE time < DATE_SUB(NOW(), INTERVAL 14 day);
DELETE FROM `gawm`.`rates` WHERE time < DATE_SUB(NOW(), INTERVAL 14 day);