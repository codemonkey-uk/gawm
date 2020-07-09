#!/bin/bash
mysql -u$GAWM_DB_USER -p$GAWM_DB_PWD -h$GAWM_DB_HOST gawm < src/setup.sql 