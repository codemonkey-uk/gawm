CREATE TABLE IF NOT EXISTS `gawm`.`games` ( 
`uid` int NOT NULL AUTO_INCREMENT, 
`time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
`data` JSON NOT NULL , 
UNIQUE `GameUIDIndex` (`uid`)) 
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `gawm`.`stats` ( 
`date` DATE NOT NULL , 
`action` CHAR(128) NOT NULL , 
`detail_type` CHAR(64) NOT NULL , 
`detail` TINYINT NOT NULL , 
`count` INT NOT NULL DEFAULT '1',
UNIQUE `EventIndex` (`date`, `action`, `detail_type`, `detail`)) 
ENGINE = InnoDB;
