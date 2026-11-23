-- Estação Commit: migrations da expansão (visitantes, bots e seguidores) em SQL do PostgreSQL.
--
-- O Render Free não roda migrations. Use este arquivo no SQL Editor do Neon, UMA vez,
-- ANTES de publicar o código desta expansão. É o equivalente exato das migrations:
--   2026_11_23_000000_add_guest_profiles_and_participant_kinds
--   2026_11_23_000100_create_follows_table
-- (gerado pelo próprio Laravel em modo "pretend", sem conectar a nenhum banco).
--
-- Pré-requisito: as migrations de 22/11 (empty_since e notifications) já aplicadas.
-- Execute tudo de uma vez; o editor do Neon roda como uma sequência de comandos.
-- Se algum comando disser que a coluna/tabela já existe, PARE e avise: parte já foi aplicada.
--
-- Para desfazer (só se necessário): ver os métodos down() das duas migrations.

-- 2026_11_23_000000_add_guest_profiles_and_participant_kinds
create table "guest_profiles" ("id" uuid not null, "display_name" varchar(24) not null, "name_key" varchar(24) not null, "avatar_style" varchar(16) not null, "avatar_seed" varchar(32) not null, "token_hash" varchar(64) not null, "last_seen_at" timestamp(0) without time zone not null, "expires_at" timestamp(0) without time zone not null, "converted_user_id" uuid null, "created_at" timestamp(0) without time zone null, "updated_at" timestamp(0) without time zone null);
alter table "guest_profiles" add primary key ("id");
alter table "guest_profiles" add constraint "guest_profiles_name_key_unique" unique ("name_key");
alter table "guest_profiles" add constraint "guest_profiles_token_hash_unique" unique ("token_hash");
create index "guest_profiles_expires_at_index" on "guest_profiles" ("expires_at");
create index "guest_profiles_converted_user_id_index" on "guest_profiles" ("converted_user_id");
alter table "room_participants" alter column "user_id" type uuid, alter column "user_id" drop not null, alter column "user_id" drop default, alter column "user_id" drop identity if exists;
comment on column "room_participants"."user_id" is NULL;
alter table "room_participants" add column "guest_id" uuid null;
alter table "room_participants" add column "kind" varchar(10) not null default 'user';
alter table "room_participants" add column "bot_key" varchar(40) null;
alter table "room_participants" add column "is_host" boolean not null default '0';
alter table "room_participants" add constraint "room_participants_guest_id_foreign" foreign key ("guest_id") references "guest_profiles" ("id") on delete cascade;
alter table "room_participants" add constraint "room_participants_room_id_guest_id_unique" unique ("room_id", "guest_id");
alter table "room_participants" add constraint "room_participants_room_id_bot_key_unique" unique ("room_id", "bot_key");
alter table "game_rooms" alter column "host_user_id" type uuid, alter column "host_user_id" drop not null, alter column "host_user_id" drop default, alter column "host_user_id" drop identity if exists;
comment on column "game_rooms"."host_user_id" is NULL;
alter table "game_rooms" add column "host_guest_id" uuid null;
alter table "game_rooms" add column "bot_slots" smallint not null default '0';
alter table "game_rooms" add constraint "game_rooms_host_guest_id_foreign" foreign key ("host_guest_id") references "guest_profiles" ("id") on delete cascade;
create index "game_rooms_host_guest_id_index" on "game_rooms" ("host_guest_id");
update "room_participants" set "is_host" = true where exists (select * from "game_rooms" where "game_rooms"."id" = "room_participants"."room_id" and "game_rooms"."host_user_id" = "room_participants"."user_id");
ALTER TABLE room_participants ADD CONSTRAINT room_participants_one_identity CHECK (
                (kind = 'user'  AND user_id IS NOT NULL AND guest_id IS NULL AND bot_key IS NULL) OR
                (kind = 'guest' AND guest_id IS NOT NULL AND user_id IS NULL AND bot_key IS NULL) OR
                (kind = 'bot'   AND bot_key IS NOT NULL AND user_id IS NULL AND guest_id IS NULL));
ALTER TABLE game_rooms ADD CONSTRAINT game_rooms_one_host CHECK (
                (host_user_id IS NOT NULL AND host_guest_id IS NULL) OR
                (host_user_id IS NULL AND host_guest_id IS NOT NULL));

-- 2026_11_23_000100_create_follows_table
create table "follows" ("id" bigserial not null primary key, "follower_id" uuid not null, "followed_id" uuid not null, "created_at" timestamp(0) without time zone not null default CURRENT_TIMESTAMP);
alter table "follows" add constraint "follows_follower_id_followed_id_unique" unique ("follower_id", "followed_id");
create index "follows_followed_id_created_at_index" on "follows" ("followed_id", "created_at");
create index "follows_follower_id_created_at_index" on "follows" ("follower_id", "created_at");
alter table "follows" add constraint "follows_follower_id_foreign" foreign key ("follower_id") references "users" ("id") on delete cascade;
alter table "follows" add constraint "follows_followed_id_foreign" foreign key ("followed_id") references "users" ("id") on delete cascade;
ALTER TABLE follows ADD CONSTRAINT follows_not_self CHECK (follower_id <> followed_id);

-- Registra as migrations como aplicadas (sem isso, um "migrate" futuro tentaria criá-las de novo).
INSERT INTO migrations (migration, batch)
SELECT m, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (VALUES ('2026_11_23_000000_add_guest_profiles_and_participant_kinds'),
             ('2026_11_23_000100_create_follows_table')) AS v(m);
