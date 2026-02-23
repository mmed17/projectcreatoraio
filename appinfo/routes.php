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
            "name" => "project_api#context",
            "url" => "/api/v1/projects/context",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#get",
            "url" => "/api/v1/projects/{projectId}",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#getCardVisibility",
            "url" => "/api/v1/projects/{projectId}/card-visibility",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#updateCardVisibility",
            "url" => "/api/v1/projects/{projectId}/card-visibility",
            "verb" => "PUT",
        ],
        [
            "name" => "project_api#listMembers",
            "url" => "/api/v1/projects/{projectId}/members",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#addMember",
            "url" => "/api/v1/projects/{projectId}/members",
            "verb" => "POST",
        ],
        // Legacy single-note endpoints (for backward compatibility)
        [
            "name" => "project_api#updateNotes",
            "url" => "/api/v1/projects/{projectId}/notes",
            "verb" => "PUT",
        ],
        // New multi-note endpoints
        [
            "name" => "project_api#listNotes",
            "url" => "/api/v1/projects/{projectId}/notes/list",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#getNote",
            "url" => "/api/v1/projects/{projectId}/notes/{noteId}",
            "verb" => "GET",
        ],
        [
            "name" => "project_api#createNote",
            "url" => "/api/v1/projects/{projectId}/notes",
            "verb" => "POST",
        ],
        [
            "name" => "project_api#updateNote",
            "url" => "/api/v1/projects/{projectId}/notes/{noteId}",
            "verb" => "PUT",
        ],
        [
            "name" => "project_api#deleteNote",
            "url" => "/api/v1/projects/{projectId}/notes/{noteId}",
            "verb" => "DELETE",
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
            "name" => "project_api#getWhiteboardInfo",
            "url" => "/api/v1/projects/{projectId}/whiteboard",
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
            "name" => "timeline_api#summary",
            "url" => "/api/v1/projects/{projectId}/timeline/summary",
            "verb" => "GET",
        ],
        [
            "name" => "timeline_api#syncDone",
            "url" => "/api/v1/projects/{projectId}/timeline/sync-done",
            "verb" => "POST",
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
