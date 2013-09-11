/*  for php  */
CREATE TABLE sid_num_generator(id int IDENTITY(1,1) NOT NULL PRIMARY KEY,tmp CHAR(1));
GO
CREATE PROCEDURE getSidNum 
AS SET NOCOUNT ON
BEGIN
  insert into sid_num_generator (tmp) values ('');
  delete from sid_num_generator where id=SCOPE_IDENTITY();  
  DECLARE  @tttt bigint;
  set @tttt=SCOPE_IDENTITY();	
  select @tttt;
  -- to test 1) delete all 2) select scope...
END;
GO
/* whith return value in var */
CREATE PROCEDURE getSidNumReturn @rzlt bigint output
AS SET NOCOUNT ON
BEGIN
  insert into sid_num_generator (tmp) values ('');
  delete from sid_num_generator where id=SCOPE_IDENTITY();  
  set @rzlt=SCOPE_IDENTITY();	
END;