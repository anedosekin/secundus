ALTER TABLE cities DROP CONSTRAINT fkcountry;
ALTER TABLE buildings DROP CONSTRAINT fkstreets;
ALTER TABLE streets DROP CONSTRAINT fkcity;
ALTER TABLE mailoffices DROP CONSTRAINT fkmailbuilding;

DROP TABLE buildings;

DROP TABLE cities;

DROP TABLE countries;

DROP TABLE streets;

DROP TABLE mailoffices;

