CREATE TABLE IF NOT EXISTS users (
    id serial PRIMARY KEY,
    username varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    valid_ts bigint DEFAULT NULL,
    is_confirmed boolean DEFAULT false,
    UNIQUE(email)
);

CREATE TABLE IF NOT EXISTS emails (
    id serial PRIMARY KEY,
    email varchar(255) NOT NULL,
    is_checked boolean DEFAULT false,
    is_valid boolean DEFAULT false,
    UNIQUE(email)
);

CREATE TABLE IF NOT EXISTS logs (
    id serial PRIMARY KEY,
    email varchar(255) NOT NULL,
    sent_at_ts bigint NOT NULL,
    is_success boolean DEFAULT false
);
