-- -*- coding: utf-8 mode: sql -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
-- @sqlmig admin

create user 'pv_jury_int' identified by 'pv_jury';
grant all privileges on pv_jury.* to 'pv_jury_int';
