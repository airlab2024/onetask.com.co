<?php

return [
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('JIRA_ALLOWED_HOSTS', ''))))),
    'max_pages' => 100,
    'max_selected' => 50,
];
