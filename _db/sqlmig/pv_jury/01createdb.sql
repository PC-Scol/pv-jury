-- -*- coding: utf-8 mode: sql -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
-- @sqlmig create

create database @@database@@;

create user if not exists 'admin' identified by 'admin';
grant all privileges on *.* to 'admin' with grant option;

create user if not exists 'monitor' identified by 'monitor';
grant usage on *.* to 'monitor';
