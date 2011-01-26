-- GameSnapper database schema.

-- Providers of games.
CREATE TABLE vendor (
id              SERIAL PRIMARY KEY,
name            VARCHAR(128) NOT NULL,
url             VARCHAR(128) NOT NULL,

UNIQUE(name),
UNIQUE(url)
);

GRANT SELECT ON vendor TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON vendor TO neoanime_gsrw;
GRANT ALL PRIVILEGES ON vendor_id_seq TO neoanime_gsrw;


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
thumbtype       VARCHAR(8) NOT NULL,
vendor_id       INTEGER,

UNIQUE (title),
UNIQUE (slug),
CONSTRAINT game_valid_active CHECK (active = 0 OR active = 1),
FOREIGN KEY (vendor_id) REFERENCES vendor (id)
);

GRANT SELECT ON game TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON game TO neoanime_gsrw;
GRANT ALL PRIVILEGES ON game_id_seq TO neoanime_gsrw;


CREATE TABLE vendor_feed (
id              SERIAL PRIMARY KEY,
vendor_id       INTEGER NOT NULL,
title           VARCHAR(128) NOT NULL,

-- First downloaded from a vendor.
added           TIMESTAMP NOT NULL DEFAULT NOW(),

-- Updates such as being banned.
modified        TIMESTAMP NOT NULL DEFAULT NOW(),

banned          SMALLINT NOT NULL,
game_id         INTEGER,

CONSTRAINT vendor_feed_valid_banned CHECK (banned = 0 OR banned = 1),
FOREIGN KEY (vendor_id) REFERENCES vendor (id),
FOREIGN KEY (game_id) REFERENCES game (id)
);

GRANT SELECT ON vendor_feed TO neoanime_gsro;
GRANT SELECT, INSERT, UPDATE ON vendor_feed TO neoanime_gsrw;
GRANT ALL PRIVILEGES ON vendor_feed_id_seq TO neoanime_gsrw;


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
homepage        SMALLINT NOT NULL,

UNIQUE (title),
CONSTRAINT category_valid_homepage CHECK (homepage = 0 OR homepage = 1)
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
