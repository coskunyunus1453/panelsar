<?php

return [
    'created' => 'Site created.',
    'deleted' => 'Site deleted.',
    'invalid_hostname' => 'Invalid or unsafe hostname format.',
    'primary_must_be_fqdn' => 'Primary domain must be a full hostname (e.g. example.com).',
    'hostname_already_taken' => 'This hostname is already used as a site, subdomain, or alias.',
    'subdomain_must_end_with_parent' => 'Subdomain must end with the site primary domain (e.g. blog.example.com).',
    'subdomain_invalid_prefix' => 'Invalid subdomain (empty prefix).',
    'invalid_path_segment' => 'path_segment may only contain lowercase letters, digits, hyphen and underscore.',
    'path_segment_required_for_nested' => 'path_segment is required for nested subdomains.',
    'path_segment_in_use' => 'This path_segment is already used on this site.',
    'alias_same_as_primary' => 'Alias cannot be the same as the primary domain.',
    'subdomain_added' => 'Subdomain added.',
    'subdomain_removed' => 'Subdomain removed.',
    'subdomain_not_found' => 'Subdomain not found.',
    'subdomain_db_rollback' => 'Database error; the subdomain was rolled back on the server. Contact support.',
    'alias_added' => 'Domain alias added.',
    'alias_removed' => 'Domain alias removed.',
    'alias_not_found' => 'Alias not found.',
    'alias_db_rollback' => 'Database error; the alias was rolled back on the server. Contact support.',
];
