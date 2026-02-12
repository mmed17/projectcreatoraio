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
            'name' => 'project_api#searchUsers',
            'url' => '/api/v1/users/search',
            'verb' => 'GET'
        ],
        [
            "name" => "project_api#getProjectByCircleId",
            "url" => "/api/v1/projects/circle/{circleId}",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#getByBoardId",
            "url" => "/api/v1/projects/board/{boardId}",
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
        // Timeline API routes
        [
            "name" => "timeline_api#index",
            "url" => "/api/v1/projects/{projectId}/timeline",
            "verb" => "GET",
        ],
        [
            "name" => "timeline_api#create",
            "url" => "/api/v1/projects/{projectId}/timeline",
            "verb" => "POST",
        ],
        [
            "name" => "timeline_api#update",
            "url" => "/api/v1/projects/{projectId}/timeline/{id}",
            "verb" => "PUT",
        ],
        [
            "name" => "timeline_api#destroy",
            "url" => "/api/v1/projects/{projectId}/timeline/{id}",
            "verb" => "DELETE",
        ],
        [
            "name" => "page#index",
            "url" => "/",
            "verb" => "GET",
        ],
    ]
];
