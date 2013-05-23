CREATE TABLE sid_num_generator (id BIGINT NOT NULL AUTO_INCREMENT, tmp CHAR(1),PRIMARY KEY (id));
delimiter $$
DROP FUNCTION IF EXISTS getSidNum;$$
CREATE FUNCTION getSidNum() 
RETURNS bigint
BEGIN
  insert into sid_num_generator (tmp) values ('');
  delete from sid_num_generator where id=LAST_INSERT_ID()-1;
  RETURN LAST_INSERT_ID();
END$$
delimiter ;