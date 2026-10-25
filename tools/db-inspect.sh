#!/usr/bin/env bash
# Read-only inspection of the local XAMPP database. Makes NO modifications.
MYSQL="/c/xampp/mysql/bin/mysql.exe"
echo "=== server version ==="
"$MYSQL" -u root -e "SELECT VERSION() AS version, @@version_comment AS comment;"
echo "=== databases ==="
"$MYSQL" -u root -e "SHOW DATABASES;"
echo "=== tables in nu ==="
"$MYSQL" -u root -e "SHOW TABLES FROM nu;"
echo "=== nu charset ==="
"$MYSQL" -u root -e "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='nu';"
echo "=== spatial capability probe (no DDL) ==="
"$MYSQL" -u root -e "SELECT ST_Distance_Sphere(POINT(31.2357,30.0444), POINT(31.2001,30.0131)) AS metres;"
