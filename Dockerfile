# -*- coding: utf-8 mode: dockerfile -*- vim:sw=4:sts=4:et:ai:si:sta:fenc=utf-8
ARG PRIVAREG
FROM ${PRIVAREG}pv-jury-app/php-apache

ARG XDEBUG
RUN if [ -n "$XDEBUG" ]; then /g/php-exts/enable-xdebug; fi

ENV RUNPHP_MODE=docker

ENV MSMTP_ENABLE=1
ENV UPDATE_CRON_FILES="php"
COPY _web/msmtp/ /msmtp-config/
COPY _web/apache/ /apache-config/
COPY _web/php/ /php-config/
COPY _web/ssl/ /ssl-config/
COPY _web/app.env /g/initenv.d/app.env
COPY _web/before-start-apache /

COPY _cron/config/ /cron-config/

WORKDIR /data/pv-jury

#HEALTHCHECK CMD curl -fs http://localhost/_hk.php
HEALTHCHECK none
