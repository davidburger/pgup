# Simple PHP Postgres database migration tool

------------

Useful for synchronization of database changes within development team. 

## Prerequisities
- psql client must be installed
- PDO_PGSQL driver is required for sync_mode = 'database' 

## Basic principles
- if _project_root_dir_/migrations folder does not exists, it will be created with initial config file
- password for individual hosts are stored in ~.pgpass - see http://www.postgresql.org/docs/9.5/interactive/libpq-pgpass.html
- if ~.pgpass does not exist, it is created automatically
- sql files could be successfully processed only once for given environment - they are checked for their equivalent stored in "applied" folder (sync_mode = filesystem) or in the database table "migration" (sync_mode = database) 
- output is written to the path defined by 'log_dir' configuration variable 
 
## Usage
- create empty sql migration file from template:
```sh
php vendor/bin/pgup create --comment="add_new_table"
```
- process migration files:
```sh
php vendor/bin/pgup
```
- process migration files for specific environment:
```sh
php vendor/bin/pgup --env=development
```
