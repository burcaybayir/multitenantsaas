-- Least-privilege grants for the application user.
--
-- The app user must be able to create one database *and one MySQL user* per
-- tenant (PermissionControlledMySQLDatabaseManager), so it needs CREATE USER
-- and GRANT OPTION, but only on the tenant_* schema namespace. It has no
-- rights on the `mysql` system schema or any other database.
CREATE DATABASE IF NOT EXISTS `installhub_testing`;

GRANT ALL PRIVILEGES ON `installhub`.* TO 'installhub'@'%';
GRANT ALL PRIVILEGES ON `installhub\_testing`.* TO 'installhub'@'%';
GRANT ALL PRIVILEGES ON `tenant\_%`.* TO 'installhub'@'%' WITH GRANT OPTION;
GRANT CREATE USER ON *.* TO 'installhub'@'%';

FLUSH PRIVILEGES;
