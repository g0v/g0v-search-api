#!/bin/sh

PRG="$0"
scriptdir=`dirname "$PRG"`
cd $scriptdir

php ./hackfoldr/import.php && \
php ./hackpad/import.php && \
php ./ircbot/import.php && \
php ./repo/import.php
