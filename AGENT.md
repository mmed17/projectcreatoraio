# ProjectCreatorAIO - Current Structure Documentation

## Overview
ProjectCreatorAIO is a Nextcloud application designed to automate the creation and management of collaborative projects by orchestrating resources across several Nextcloud apps (Circles, Deck, Files, Contacts).

## File System Structure
```
projectcreatoraio/
├── appinfo/             # App metadata and routing
├── css/                 # Compiled styles
├── img/                 # SVG assets and icons
├── js/                  # Compiled frontend assets
├── lib/                 # Backend PHP Logic
│   ├── AppInfo/         # Application entry point
│   ├── Controller/      # API and Page controllers
│   ├── Dashboard/       # Dashboard widget integration
│   ├── Db/              # Database models and mappers (Entities)
│   ├── Migration/       # Database schema versioning
│   ├── Service/         # Business logic (Project orchestration, File tree)
│   └── Utils/           # Helper utilities
├── src/                 # Frontend Source (Vue 2.7)
│   ├── components/      # Vue UI components
│   ├── Models/          # Frontend data models
│   ├── Services/        # API client wrappers
│   └── macros/          # Constants and shared logic
├── templates/           # PHP templates (main entry point)
└── tests/               # PHPUnit and integration tests
```

## Database Schema

### 1. `custom_projects`
Main table storing project metadata and integration links.
- **Project Info:** `id`, `name`, `label`, `number`, `type`, `description`, `status`.
- **Client Info:** `client_name`, `client_role`, `client_phone`, `client_email`, `client_address`.
- **Location:** `loc_street`, `loc_city`, `loc_zip`.
- **Integration IDs:** `owner_id`, `circle_id`, `board_id`, `folder_id`, `folder_path`, `organization_id`, `white_board_id`.

### 2. `project_timeline_items`
Stores milestones and tasks for the project timeline.
- **Fields:** `id`, `project_id`, `label`, `start_date`, `end_date`, `color`, `order_index`.

### 3. `proj_private_folders`
Links users to specific private folders within a project context.
- **Fields:** `id`, `project_id`, `user_id`, `folder_id`, `folder_path`.

## Backend Architecture

### Services
- **`ProjectService`**: The core orchestrator. Manages the lifecycle of a project, including creating Circles, Deck boards, Group Folders, and direct filesystem manipulations.
- **`FileTreeService`**: Recursively builds a JSON representation of Nextcloud file nodes.

### API Endpoints
- `POST /api/v1/projects`: Creates a new project with all external integrations.
- `GET /api/v1/projects/list`: Lists accessible projects.
- `GET /api/v1/projects/{id}/files`: Aggregated file tree (Shared + Private).
- `GET /api/v1/projects/{id}/timeline`: CRUD for project milestones.

## Frontend Architecture

### Framework
- **Vue 2.7** using **Nextcloud Vue Components (@nextcloud/vue)**.
- **Bundler**: Vite.

### Key Components
- **`ProjectCreator.vue`**: Multi-field form for project initialization.
- **`ProjectsWidget.vue`**: Searchable project list with administrative filters.
- **`UsersFetcher.vue`**: Integration with Nextcloud user search.

## External Dependencies
- **Circles**: For member management and access control.
- **Deck**: For task tracking and boards.
- **Files/Group Folders**: For shared storage.
- **Contacts**: Project members are often linked via Circles to the Contacts app.
