#!/usr/bin/env bash
set -euo pipefail

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_test_%\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
