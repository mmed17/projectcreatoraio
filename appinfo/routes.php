<?php

return [
    "routes" => [
        [
            "name" => "project_api#create",
            "url" => "/api/v1/projects",
            "verb" => "POST",
        ],
        [
            "name" => "project_api#list",
            "url" => "/api/v1/projects/list",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#get",
            "url" => "/api/v1/projects/{projectId}",
            "verb" => "GET",
        ],
        [
            'name' => 'project_api#listByUser',
            'url' => '/api/v1/users/{userId}/projects',
            'verb' => 'GET'
        ],
        [
            "name" => "project_api#getProjectByCircleId",
            "url" => "/api/v1/projects/circle/{circleId}",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#getProjectFiles",
            "url" => "/api/v1/projects/{projectId}/files",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#update",
            "url" => "/api/v1/projects/{id}", 
            "verb" => "PUT",
        ],
        [
            "name" => "page#index",
            "url" => "/",
            "verb" => "GET",
        ],
    ]
];