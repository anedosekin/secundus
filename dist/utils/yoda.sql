CREATE TABLE buildings
(
  street_name character varying,
  id numeric NOT NULL,
  building_number numeric,
  city_id numeric,
  CONSTRAINT pkbuildings PRIMARY KEY (id)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE cities
(
  id numeric NOT NULL,
  city_name character varying NOT NULL,
  capital boolean,
  country character varying,
  CONSTRAINT citiespk PRIMARY KEY (id)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE countries
(
  country_name character varying NOT NULL,
  CONSTRAINT countriespk PRIMARY KEY (country_name)
)
WITH (
  OIDS=FALSE
);

CREATE TABLE streets
(
  street_name character varying NOT NULL,
  city_id numeric NOT NULL,
  CONSTRAINT pkstreets PRIMARY KEY (city_id, street_name)
)
WITH (
  OIDS=FALSE
);

ALTER TABLE countries
  OWNER TO serious;
ALTER TABLE cities
  OWNER TO serious;
ALTER TABLE buildings
  OWNER TO serious;
ALTER TABLE streets
  OWNER TO serious;
  
INSERT INTO countries(country_name) VALUES ('Россия');
INSERT INTO countries(country_name) VALUES ('Франция');
INSERT INTO countries(country_name) VALUES ('США');
INSERT INTO countries(country_name) VALUES ('Германия');


INSERT INTO cities(id, city_name, capital, country) VALUES (1, 'Москва', TRUE, 'Россия');
INSERT INTO cities(id, city_name, capital, country) VALUES (2, 'Санкт-Петербург', FALSE, 'Россия');
INSERT INTO cities(id, city_name, capital, country) VALUES (3, 'Владивосток', FALSE, 'Россия');
INSERT INTO cities(id, city_name, capital, country) VALUES (4, 'Париж', TRUE, 'Франция');
INSERT INTO cities(id, city_name, capital, country) VALUES (5, 'Марсель', FALSE, 'Франция');
INSERT INTO cities(id, city_name, capital, country) VALUES (6, 'Лиль', FALSE, 'Франция');
INSERT INTO cities(id, city_name, capital, country) VALUES (7, 'Берлин', TRUE, 'Германия');
INSERT INTO cities(id, city_name, capital, country) VALUES (8, 'Бремен', FALSE, 'Германия');
INSERT INTO cities(id, city_name, capital, country) VALUES (9, 'Мюнхен', FALSE, 'Германия');
INSERT INTO cities(id, city_name, capital, country) VALUES (10, 'Вашингтон', TRUE, 'США');
INSERT INTO cities(id, city_name, capital, country) VALUES (11, 'Нью-Йорк', FALSE, 'США');
INSERT INTO cities(id, city_name, capital, country) VALUES (12, 'Бостон', FALSE, 'США');


INSERT INTO streets(street_name, city_id) VALUES ('Арбат', 1);
INSERT INTO streets(street_name, city_id) VALUES ('Остоженка', 1);
INSERT INTO streets(street_name, city_id) VALUES ('Пречистенка', 1);
INSERT INTO streets(street_name, city_id) VALUES ('Ленина', 1);
INSERT INTO streets(street_name, city_id) VALUES ('Невский проспект', 2);
INSERT INTO streets(street_name, city_id) VALUES ('Набережная реки Мойки', 2);
INSERT INTO streets(street_name, city_id) VALUES ('Ленина', 2);
INSERT INTO streets(street_name, city_id) VALUES ('Врангеля', 3);
INSERT INTO streets(street_name, city_id) VALUES ('Ленина', 3);
INSERT INTO streets(street_name, city_id) VALUES ('Монмартр', 4);
INSERT INTO streets(street_name, city_id) VALUES ('Елисейские поля', 4);
INSERT INTO streets(street_name, city_id) VALUES ('Деголя', 4);
INSERT INTO streets(street_name, city_id) VALUES ('Лефевр', 5);
INSERT INTO streets(street_name, city_id) VALUES ('Буиньон', 5);
INSERT INTO streets(street_name, city_id) VALUES ('Сэндени', 6);
INSERT INTO streets(street_name, city_id) VALUES ('Блумштрассе', 7);
INSERT INTO streets(street_name, city_id) VALUES ('Карлсгартен', 7);
INSERT INTO streets(street_name, city_id) VALUES ('Фридрихплац', 7);
INSERT INTO streets(street_name, city_id) VALUES ('Райнкольштрассе', 8);
INSERT INTO streets(street_name, city_id) VALUES ('Фрайгартен', 8);
INSERT INTO streets(street_name, city_id) VALUES ('Бергхаузен', 9);
INSERT INTO streets(street_name, city_id) VALUES ('Цвайриверплац', 9);
INSERT INTO streets(street_name, city_id) VALUES ('Ридж плейс', 10);
INSERT INTO streets(street_name, city_id) VALUES ('Гуд хоуп роуд', 10);
INSERT INTO streets(street_name, city_id) VALUES ('Массачусетс авеню', 10);
INSERT INTO streets(street_name, city_id) VALUES ('Ньюэл стрит', 11);
INSERT INTO streets(street_name, city_id) VALUES ('Кингсленд авеню', 11);
INSERT INTO streets(street_name, city_id) VALUES ('Нассау авеню', 11);
INSERT INTO streets(street_name, city_id) VALUES ('Сноу плэйс', 12);
INSERT INTO streets(street_name, city_id) VALUES ('Конгресс-стрит', 12);
INSERT INTO streets(street_name, city_id) VALUES ('Дерн-стрит', 12);

INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Арбат', 1, 1, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Остоженка', 1, 2, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Пречистенка', 1, 3, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Ленина', 1, 4, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Невский проспект', 2, 5, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Набережная реки Мойки', 2, 6, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Ленина', 2, 7, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Врангеля', 3, 8, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Ленина', 3, 9, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Монмартр', 4, 10, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Елисейские поля', 4, 11, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Деголя', 4, 12, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Лефевр', 5, 13, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Буиньон', 5, 14, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Сэндени', 6, 15, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Блумштрассе', 7, 16, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Карлсгартен', 7, 17, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Фридрихплац', 7, 18, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Райнкольштрассе', 8, 19, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Фрайгартен', 8, 20, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Бергхаузен', 9, 21, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Цвайриверплац', 9, 22, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Ридж плейс', 10, 23, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Гуд хоуп роуд', 10, 24, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Массачусетс авеню', 10, 25, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Ньюэл стрит', 11, 26, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Кингсленд авеню', 11, 27, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Нассау авеню', 11, 28, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Сноу плэйс', 12, 29, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Конгресс-стрит', 12, 30, 4);
INSERT INTO buildings(street_name, city_id, id, building_number) VALUES ('Дерн-стрит', 12, 31, 4);


ALTER TABLE cities ADD CONSTRAINT fkcountry FOREIGN KEY (country)
      REFERENCES countries (country_name) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION;
ALTER TABLE buildings ADD CONSTRAINT fkstreets FOREIGN KEY (city_id, street_name)
      REFERENCES streets (city_id, street_name) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION;
ALTER TABLE streets ADD CONSTRAINT fkcity FOREIGN KEY (city_id)
      REFERENCES cities (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION;