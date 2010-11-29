

CREATE TABLE game (
id              SERIAL PRIMARY KEY,
title           VARCHAR(128) NOT NULL,
played          INTEGER NOT NULL DEFAULT 0,
description     VARCHAR(1024) NOT NULL,
instructions    VARCHAR(1024),
added           TIMESTAMP NOT NULL DEFAULT NOW(),
filepath        VARCHAR(16) NOT NULL,
slug            VARCHAR(64) NOT NULL,
active          SMALLINT NOT NULL,
width           SMALLINT NOT NULL,
height          SMALLINT NOT NULL,

UNIQUE (title),
UNIQUE (slug),
CONSTRAINT game_valid_active CHECK (active = 0 OR active = 1)
);

GRANT SELECT ON game TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON game TO neoanime_gsrw;
GRANT ALL PRIVILEGES ON game_id_seq TO neoanime_gsrw;


CREATE TABLE rating (
game_id         INTEGER NOT NULL,
rating          INTEGER NOT NULL,

FOREIGN KEY (game_id) REFERENCES game (id)
);

GRANT SELECT ON rating TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON rating TO neoanime_gsrw;


CREATE TABLE category (
id              SERIAL PRIMARY KEY,
title           VARCHAR(64) NOT NULL,

UNIQUE (title)
);

GRANT SELECT ON category TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON category TO neoanime_gsrw;
GRANT ALL PRIVILEGES ON category_id_seq TO neoanime_gsrw;


CREATE TABLE category_game_xref (
game_id         INTEGER NOT NULL,
category_id     INTEGER NOT NULL,

FOREIGN KEY (game_id) REFERENCES game (id),
FOREIGN KEY (category_id) REFERENCES category (id),
CONSTRAINT category_game_xref_pkey PRIMARY KEY (game_id, category_id)
);

GRANT SELECT ON category_game_xref TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON category_game_xref TO neoanime_gsrw;
