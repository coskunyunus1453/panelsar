<?php

return [
    'created' => 'Database created successfully.',
    'deleted' => 'Database deleted successfully.',
    'updated' => 'Database updated successfully.',
    'password_rotated' => 'Database password rotated successfully.',
    'rotate_mysql_only' => 'Password rotation is only available for MySQL databases.',
    'rotate_password_unsupported' => 'Password rotation is not available for this database type.',
    'grant_host_mysql_only' => 'Connection host policy applies only to MySQL.',
    'update_no_changes' => 'Provide at least one field to update.',
    'password_unreadable' => 'The stored database password could not be decrypted. Enter a new password or use «Rotate password».',
    'credentials_sync_reminder' => 'The database password was updated on the server. Update WordPress (wp-config.php), Laravel (.env), and other apps to match; the panel does not rewrite those files automatically.',
    'mysql_user_missing' => 'MySQL user «:user» was not found in mysql.user. It may have been removed or the server differs.',
    'mysql_user_host_ambiguous' => 'Multiple Host entries exist for «:user» (:hosts). The panel cannot pick which @host to use; remove duplicate accounts in MySQL or clarify grant_host.',
    'provision_failed' => 'Could not provision the database on the server (check MySQL/MariaDB credentials in panel config)',

    'export_not_mysql' => 'Export is only available for MySQL databases.',
    'export_not_postgresql' => 'Export is only available for PostgreSQL databases.',
    'export_unsupported_type' => 'Export is not available for this database type.',
    'export_failed' => 'Export failed.',
    'provision_disabled_export' => 'Database tools are disabled in panel configuration; export is unavailable.',

    'import_not_mysql' => 'Import applies only to MySQL databases.',
    'import_not_postgresql' => 'Import applies only to PostgreSQL databases.',
    'import_unsupported_type' => 'Import is not available for this database type.',
    'import_failed' => 'Import failed.',
    'imported' => 'Backup imported successfully. Previous database contents were replaced.',
    'provision_disabled_import' => 'Database tools are disabled in panel configuration; import is unavailable.',
    'import_file_unreadable' => 'The uploaded file could not be read.',
    'import_sql_only' => 'Only .sql files are accepted.',
    'import_confirm_expected' => 'REPLACEALLDATA',
    'import_confirm_mismatch' => 'Confirmation phrase does not match. Type the exact phrase from your language file.',
];
