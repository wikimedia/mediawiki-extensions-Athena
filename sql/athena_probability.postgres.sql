-- Postgres version
-- TODO check it actually works
CREATE TABLE athena_probability (
  ap_id             SERIAL       PRIMARY KEY,
  ap_variable       TEXT         NOT NULL  DEFAULT '',
  ap_given          TEXT                   DEFAULT '',
  ap_value          DECIMAL      NOT NULL  DEFAULT 0.01,
  ap_updated        TIMESTAMPTZ  NOT NULL  DEFAULT now()
);
CREATE INDEX athena_probability_ap_id ON athena_probability(ap_id);
