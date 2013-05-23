create sequence getIncForSID increment by 1 start with 1;
DROP FUNCTION IF EXISTS getSidNum();
CREATE FUNCTION getSidNum() RETURNS bigint AS $$
SELECT NEXTVAL('getIncForSID');
$$LANGUAGE SQL;
